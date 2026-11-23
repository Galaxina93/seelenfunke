<div x-data="{
        topSaved: false,
        _topSaveTimer: null,
        projectSavedClient: @entangle('isProjectSaved').live,
        lightboxOpen: false,
        lightboxUrl: '',
        lightboxLabel: '',
        openStudioLightbox(url, label) {
            if (!url) return;
            this.lightboxUrl = url;
            this.lightboxLabel = label || 'Vorschau';
            this.lightboxOpen = true;
        },
        flashTopSaved() {
            this.topSaved = true;
            this.projectSavedClient = true;
            if (this._topSaveTimer) clearTimeout(this._topSaveTimer);
            this._topSaveTimer = setTimeout(() => { this.topSaved = false; }, 2500);
        }
     }"
     @laser-studio-top-saved.window="flashTopSaved()"
     @laser-freehand-stage-dirty.window="projectSavedClient = false"
     @laser-freehand-stage-saved.window="projectSavedClient = true"
     class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 shadow-sm dark:shadow-2xl space-y-6">

    {{-- TOP KOPFZEILE --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 border-b border-gray-100 dark:border-gray-800">
        <div class="flex flex-wrap items-center gap-3">
            @if($activeProjectId)
                <button type="button"
                        wire:click="closeProject"
                        class="px-3.5 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white text-xs font-black uppercase tracking-wider flex items-center gap-1.5 cursor-pointer transition-colors">
                    <x-heroicon-m-arrow-left class="w-4 h-4 text-[var(--theme-color)]" />
                    <span>Projekte</span>
                </button>
            @endif

            <h2 class="text-gray-900 dark:text-white font-serif font-bold text-xl sm:text-2xl tracking-wide flex items-center gap-2.5">
                <x-heroicon-o-viewfinder-circle class="w-7 h-7 text-[var(--theme-color)] shrink-0" />
                <span>Laser-Studio · Universeller Projektkonfigurator</span>
            </h2>

            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]"
                  title="Gestalte eigene Karten, QR-Aufsteller, Muster oder Kunden-Gegenstände direkt auf dem echten Live-Kamerabild des xTool F2 Ultra UV.">
                xTool F2 Ultra UV Live
            </span>
        </div>

        @if(!$activeProjectId)
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        wire:click="createProject('card_86x54')"
                        class="px-4 py-2.5 rounded-xl bg-[var(--theme-color)] text-gray-950 font-black text-[10px] uppercase tracking-widest flex items-center gap-1.5 shadow-xs hover:opacity-95 cursor-pointer transition-all">
                    <x-heroicon-m-plus class="w-4 h-4" />
                    <span>QR- / Geschenkkarte (86×54 mm)</span>
                </button>
                <button type="button"
                        wire:click="createProject('free_bed')"
                        class="px-4 py-2.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] text-gray-900 dark:text-white font-black text-[10px] uppercase tracking-widest flex items-center gap-1.5 cursor-pointer transition-all">
                    <x-heroicon-m-camera class="w-4 h-4 text-[var(--theme-color)]" />
                    <span>Freies Kamera-Bett (200×200 mm)</span>
                </button>
                <button type="button"
                        wire:click="$set('showNewProjectModal', true)"
                        class="px-4 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-bold text-xs cursor-pointer transition-colors">
                    Eigenes Maß / Rohling...
                </button>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        wire:click="saveMetadataOnly(false)"
                        :class="topSaved ? 'bg-emerald-600 text-white' : 'bg-[var(--theme-color)] text-gray-950'"
                        class="px-4 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest flex items-center gap-1.5 cursor-pointer transition-all duration-300">
                    <x-heroicon-m-check class="w-4 h-4" />
                    <span x-text="topSaved ? 'Erfolgreich gespeichert!' : 'Speichern'">Speichern</span>
                </button>
                <button type="button"
                        wire:click="downloadXcsProject"
                        title="Fertige xTool Studio Projektdatei (.xcs) herunterladen"
                        class="px-3.5 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:border-[var(--theme-color)] border border-gray-200 dark:border-gray-700 font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                    <x-heroicon-m-arrow-down-tray class="w-4 h-4 text-[var(--theme-color)]" />
                    <span>.xcs</span>
                </button>
            </div>
        @endif
    </div>

    @if($statusNotice)
        <div class="px-4 py-3 rounded-2xl border text-xs font-bold flex items-center justify-between gap-3 {{ $statusNoticeType === 'error' ? 'bg-red-500/10 border-red-500/30 text-red-700 dark:text-red-300' : ($statusNoticeType === 'warning' ? 'bg-amber-500/10 border-amber-500/30 text-amber-800 dark:text-amber-300' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300') }}">
            <span>{{ $statusNotice }}</span>
            <button type="button" wire:click="$set('statusNotice', null)" class="text-current opacity-70 hover:opacity-100 cursor-pointer">✕</button>
        </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- ANSICHT 1: PROJEKTVERWALTUNG (WIE IN XTOOL CREATIVE SPACE)            --}}
    {{-- ===================================================================== --}}
    @if(!$activeProjectId)
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold">
                    <button type="button" wire:click="$set('projectFilter', 'active')" class="px-3 py-1.5 rounded-lg cursor-pointer {{ $projectFilter === 'active' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500' }}">
                        Aktiv
                    </button>
                    <button type="button" wire:click="$set('projectFilter', 'favorites')" class="px-3 py-1.5 rounded-lg cursor-pointer {{ $projectFilter === 'favorites' ? 'bg-white dark:bg-gray-800 text-[#C5A059] shadow-2xs' : 'text-gray-500' }}">
                        ★ Favoriten
                    </button>
                    <button type="button" wire:click="$set('projectFilter', 'archived')" class="px-3 py-1.5 rounded-lg cursor-pointer {{ $projectFilter === 'archived' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500' }}">
                        Archiv
                    </button>
                </div>

                <input type="text"
                       wire:model.live.debounce.250ms="projectSearch"
                       placeholder="Projekt suchen..."
                       class="w-56 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
            </div>

            @if($this->projects->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-800 p-10 text-center space-y-3 bg-gray-50/50 dark:bg-gray-950/40">
                    <x-heroicon-o-folder-open class="w-10 h-10 mx-auto text-gray-400" />
                    <div class="text-sm font-bold text-gray-800 dark:text-gray-200">Noch kein Laser-Projekt in dieser Ansicht</div>
                    <div class="flex flex-wrap justify-center gap-2 pt-1">
                        <button type="button"
                                wire:click="createProject('card_86x54')"
                                class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 font-black text-[10px] uppercase tracking-widest cursor-pointer">
                            + Erste QR- / Geschenkkarte anlegen
                        </button>
                        <button type="button"
                                wire:click="createProject('free_bed')"
                                class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white font-bold text-xs cursor-pointer">
                            + Freies Kamera-Projekt starten
                        </button>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($this->projects as $proj)
                        @php
                            $pConf = is_array($proj->configuration) ? $proj->configuration : [];
                            $frontCount = count($pConf['texts'] ?? []) + count($pConf['logos'] ?? []);
                            $backCount = count($pConf['texts_back'] ?? []) + count($pConf['logos_back'] ?? []);
                            $matLabel = (string) ($pConf['laser_cockpit_parameters']['front']['material'] ?? 'Standard');
                        @endphp
                        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70 p-4 flex flex-col justify-between gap-3 hover:border-[var(--theme-color)] transition-all">
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <button type="button"
                                            wire:click="openProject('{{ $proj->id }}')"
                                            class="text-left font-bold text-sm text-gray-900 dark:text-white hover:text-[var(--theme-color)] truncate cursor-pointer">
                                        {{ $proj->title }}
                                    </button>
                                    <button type="button"
                                            wire:click="toggleFavoriteProject('{{ $proj->id }}')"
                                            class="text-sm cursor-pointer {{ $proj->is_favorite ? 'text-[#C5A059]' : 'text-gray-400 hover:text-[#C5A059]' }}"
                                            title="Favorit">
                                        {{ $proj->is_favorite ? '★' : '☆' }}
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold">
                                    <span class="px-2 py-0.5 rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 font-mono text-gray-800 dark:text-gray-200">
                                        {{ number_format((float) $proj->width_mm, 0, ',', '.') }} × {{ number_format((float) $proj->height_mm, 0, ',', '.') }} mm
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                                        {{ $matLabel }}
                                    </span>
                                    @if($proj->has_back_side)
                                        <span class="px-2 py-0.5 rounded-md bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                            Vorder- &amp; Rückseite
                                        </span>
                                    @endif
                                    @if($proj->product)
                                        <span class="px-2 py-0.5 rounded-md bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300 truncate max-w-[140px]">
                                            {{ $proj->product->name }}
                                        </span>
                                    @endif
                                </div>

                                <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                                    <span>Elemente: {{ $frontCount + $backCount }}</span>
                                    <span>{{ $proj->updated_at ? $proj->updated_at->format('d.m.Y H:i') : '' }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-gray-200/70 dark:border-gray-800 flex items-center justify-between gap-2">
                                <button type="button"
                                        wire:click="openProject('{{ $proj->id }}')"
                                        class="px-3.5 py-1.5 rounded-xl bg-[var(--theme-color)] text-gray-950 font-black text-[10px] uppercase tracking-wider cursor-pointer">
                                    Öffnen
                                </button>

                                <div class="flex items-center gap-1">
                                    <button type="button"
                                            wire:click="duplicateProject('{{ $proj->id }}')"
                                            title="Projekt duplizieren"
                                            class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[10px] font-bold text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] cursor-pointer">
                                        Kopie
                                    </button>
                                    <button type="button"
                                            wire:click="toggleArchiveProject('{{ $proj->id }}')"
                                            class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[10px] font-bold text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] cursor-pointer">
                                        {{ $proj->status === 'archived' ? 'Aktivieren' : 'Archiv' }}
                                    </button>
                                    <button type="button"
                                            wire:click="deleteProject('{{ $proj->id }}')"
                                            wire:confirm="Laser-Projekt wirklich endgültig löschen?"
                                            title="Löschen"
                                            class="p-1.5 rounded-lg bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20 cursor-pointer">
                                        <x-heroicon-m-trash class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- ===================================================================== --}}
        {{-- ANSICHT 2: ECHTER PRODUKTKONFIGURATOR (FREE-HAND AUF LASERSCREENSHOT) --}}
        {{-- ===================================================================== --}}
        @php
            $activeProjectModel = \App\Models\Product\LaserStudioProject::with('product')->find($activeProjectId);
            $freehandInitialData = $activeProjectModel && is_array($activeProjectModel->configuration)
                ? array_merge($activeProjectModel->configuration, ['laser_studio_project_id' => $activeProjectModel->id])
                : ['laser_studio_project_id' => $activeProjectId];
            $isFreeBedMode = ($projectMode === 'free_bed') || ($projectWidthMm >= 198.0 && $projectHeightMm >= 198.0);
        @endphp

        {{-- PROJEKT-STAMMZEILE + LIVE-KAMERA-STEUERUNG --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 items-end">
            <div class="lg:col-span-4">
                <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Projekttitel</label>
                <input type="text"
                       wire:model.blur="projectTitle"
                       wire:change="saveMetadataOnly(true)"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]" />
            </div>

            <div class="lg:col-span-3 grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Breite (mm)</label>
                    <input type="number" step="0.5" min="5" max="200"
                           wire:model.blur="projectWidthMm"
                           wire:change="saveMetadataOnly(true)"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Höhe (mm)</label>
                    <input type="number" step="0.5" min="5" max="200"
                           wire:model.blur="projectHeightMm"
                           wire:change="saveMetadataOnly(true)"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white" />
                </div>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Kontur</label>
                <select wire:model.live="projectContourShape"
                        wire:change="saveMetadataOnly(true)"
                        class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-2 text-xs font-bold text-gray-900 dark:text-white">
                    <option value="rounded_card">Karte abgerundet</option>
                    <option value="rect">Rechteck / Frei</option>
                    <option value="circle">Kreis / Rund</option>
                </select>
            </div>

            <div class="lg:col-span-3 flex flex-wrap items-center justify-between lg:justify-end gap-2">
                <label class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input type="checkbox" wire:model.live="projectHasBackSide" wire:change="saveMetadataOnly(true)" class="rounded border-gray-300 text-[var(--theme-color)]" />
                    <span>Beidseitig</span>
                </label>

                @if($projectHasBackSide)
                    <div class="inline-flex p-0.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase">
                        <button type="button" wire:click="switchSide('front')" class="px-2.5 py-1.5 rounded-lg cursor-pointer {{ $activeSide === 'front' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-500' }}">
                            1/2 Vorne
                        </button>
                        <button type="button" wire:click="switchSide('back')" class="px-2.5 py-1.5 rounded-lg cursor-pointer {{ $activeSide === 'back' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-500' }}">
                            2/2 Hinten
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- HAUPT-GRID: LINKS UNSER ECHTER PRODUKTKONFIGURATOR (FREE-HAND) | RECHTS LASER-COCKPIT --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

            {{-- LINKE SPALTE (7/12): KAMERA-STATUSLEISTE + ECHTER SEELENFUNKE PRODUKTKONFIGURATOR --}}
            <div class="xl:col-span-7 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2 px-3.5 py-2.5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-mono text-[10px] font-bold {{ $laserOnline ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-gray-200/70 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                            <span class="w-2 h-2 rounded-full {{ $laserOnline ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                            <span>{{ $laserOnline ? 'Laser-Kamera Live' : 'Kamera-Ansicht (200×200 mm)' }}</span>
                            @if($cameraUpdatedAt !== '')
                                <span class="opacity-70">· {{ $cameraUpdatedAt }}</span>
                            @endif
                        </span>

                        <select wire:model.live="cameraLightMode"
                                title="Kamera-Arbeitslicht beim Schnappschuss (Blendfrei Aus verhindert Spiegelungen auf Gold/Metall)"
                                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1 text-[11px] font-bold text-gray-800 dark:text-gray-200">
                            <option value="normal">Licht: Standard</option>
                            <option value="dim">Licht: Gedimmt 10%</option>
                            <option value="anti_glare">Licht: Blendfrei Aus</option>
                        </select>
                    </div>

                    <button type="button"
                            wire:click="refreshCameraOnly"
                            wire:loading.attr="disabled"
                            class="px-3 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-wider text-gray-900 dark:text-white flex items-center gap-1 cursor-pointer">
                        <x-heroicon-m-camera class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                        <span>Kamera laden</span>
                    </button>
                </div>

                <div class="rounded-3xl overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm bg-white dark:bg-gray-950">
                    @if($activeProjectModel)
                        <livewire:shop.product.product-configurator.product-configurator
                            :product="$activeProjectModel"
                            context="laser_freehand"
                            :initialData="$freehandInitialData"
                            :key="'laser-freehand-cfg-' . $activeProjectId" />
                    @endif
                </div>
            </div>

            {{-- RECHTE SPALTE (5/12): KOORDINATEN, RAHMEN LEUCHTEN, 2-SCHRITTE-LASER & PARAMETER --}}
            <div class="xl:col-span-5 space-y-4">

                {{-- KARTE 1: LIVE-AUSRICHTUNG, RAHMEN LEUCHTEN & AN LASER SENDEN --}}
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70 p-4 space-y-3.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white">Laser-Steuerung</span>
                        @if($isFreeBedMode)
                            <span class="px-2 py-0.5 rounded-md bg-[var(--theme-color-15)] text-[var(--theme-color)] text-[10px] font-bold"
                                  title="Im freien 200×200 mm Modus umschließt 'Rahmen leuchten' automatisch exakt deine platzierten Texte & Motive (z.B. direkt auf der Scherenklinge).">
                                Smart-Rahmen: Element-Bereich
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-xs">
                        @if($isFreeBedMode)
                            <span class="inline-flex items-center gap-1.5 font-mono text-[11px] font-bold text-gray-600 dark:text-gray-300"
                                  title="Im freien 200×200 mm Kamera-Modus ist die Arbeitsfläche fest 1:1 auf das Laserbett (X=100,0 / Y=100,0 mm) verankert, damit alles exakt dort gelasert wird, wo du es auf dem Kamerabild platzierst.">
                                <span>1:1 Kamera-Bett (200×200 mm)</span>
                            </span>
                        @else
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex items-center gap-1 font-mono text-[11px]">
                                    <span class="text-gray-500 font-bold">X:</span>
                                    <input type="number" step="0.5" min="5" max="195" wire:model.blur="centerXMm" wire:change="saveMetadataOnly(true)" class="w-15 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-1.5 py-1 text-center font-bold text-gray-900 dark:text-white" />
                                </label>
                                <label class="inline-flex items-center gap-1 font-mono text-[11px]">
                                    <span class="text-gray-500 font-bold">Y:</span>
                                    <input type="number" step="0.5" min="5" max="195" wire:model.blur="centerYMm" wire:change="saveMetadataOnly(true)" class="w-15 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-1.5 py-1 text-center font-bold text-gray-900 dark:text-white" />
                                </label>
                                <label class="inline-flex items-center gap-1 font-mono text-[11px]">
                                    <span class="text-gray-500 font-bold">°:</span>
                                    <input type="number" step="0.5" min="-180" max="180" wire:model.blur="angleDeg" wire:change="saveMetadataOnly(true)" class="w-14 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg px-1.5 py-1 text-center font-bold text-gray-900 dark:text-white" />
                                </label>
                            </div>
                        @endif

                        <div class="flex items-center gap-1.5">
                            @if(!$isFreeBedMode)
                                <div class="inline-flex p-0.5 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase">
                                    <button type="button" wire:click="$set('framingMode', 'contour')" class="px-2 py-1 rounded cursor-pointer {{ $framingMode === 'contour' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-500' }}">1:1 Kontur</button>
                                    <button type="button" wire:click="$set('framingMode', 'rect')" class="px-2 py-1 rounded cursor-pointer {{ $framingMode === 'rect' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-500' }}">Rechteck</button>
                                </div>
                            @endif
                            <button type="button"
                                    wire:click="triggerFraming"
                                    wire:loading.attr="disabled"
                                    class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:border-[var(--theme-color)] border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-wider text-gray-900 dark:text-white cursor-pointer">
                                Rahmen leuchten
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <button type="button"
                                wire:click="refreshLaserCameraAndAlign"
                                wire:loading.attr="disabled"
                                class="min-h-[48px] flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border text-[11px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $workflowStep === 1 ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm' : 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)]' }}">
                            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black {{ $workflowStep > 1 ? 'bg-[var(--theme-color)] text-gray-950' : ($workflowStep === 1 ? 'bg-gray-950 text-[var(--theme-color)]' : 'bg-gray-200 dark:bg-gray-800') }}">
                                {{ $workflowStep > 1 ? '✓' : '1' }}
                            </span>
                            <span wire:loading.remove wire:target="refreshLaserCameraAndAlign">Kamera &amp; KI-Ausrichten</span>
                            <span wire:loading wire:target="refreshLaserCameraAndAlign">Richte aus...</span>
                        </button>

                        <button type="button"
                                wire:click="sendProjectDirectlyToLaser"
                                :disabled="!projectSavedClient"
                                @if(!$isProjectSaved) disabled @endif
                                :title="projectSavedClient ? 'Gespeichertes Projekt an den Laser übertragen' : 'Bitte zuerst unten im Konfigurator auf „Projekt speichern“ klicken'"
                                :class="projectSavedClient
                                    ? '{{ $workflowStep === 2 ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm cursor-pointer' : ($workflowStep >= 3 ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/40 cursor-pointer' : 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] cursor-pointer') }}'
                                    : 'bg-gray-100 dark:bg-gray-900/50 text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-800 cursor-not-allowed opacity-65'"
                                wire:loading.attr="disabled"
                                class="min-h-[48px] flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border text-[11px] font-black uppercase tracking-widest transition-all">
                            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black {{ $workflowStep >= 3 ? 'bg-emerald-500 text-gray-950' : ($workflowStep === 2 ? 'bg-gray-950 text-[var(--theme-color)]' : 'bg-gray-200 dark:bg-gray-800') }}">
                                {{ $workflowStep >= 3 ? '✓' : '2' }}
                            </span>
                            <span wire:loading.remove wire:target="sendProjectDirectlyToLaser">An Laser senden</span>
                            <span wire:loading wire:target="sendProjectDirectlyToLaser">Sende an Laser...</span>
                        </button>
                    </div>

                    @if($workflowStep >= 3 && $projectHasBackSide && $activeSide === 'front')
                        <button type="button"
                                wire:click="switchSide('back')"
                                class="w-full py-2.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/40 text-emerald-800 dark:text-emerald-300 text-xs font-black uppercase tracking-widest cursor-pointer">
                            ✓ Vorderseite bereit · Werkstück wenden &amp; Rückseite (2/2) öffnen
                        </button>
                    @endif

                    <div class="flex justify-end">
                        <button type="button"
                                wire:click="stopLaser"
                                class="px-3.5 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-600 dark:text-red-400 text-[10px] font-black uppercase tracking-widest cursor-pointer">
                            ■ Laser Stopp / Not-Aus
                        </button>
                    </div>
                </div>

                {{-- KARTE 2: LASERPARAMETER, MEDIEN-PRESETS & FOKUS (WIE SCHRITT 1 IN BESTELLUNG) --}}
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70 p-4 space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white">Laserparameter &amp; Medium</span>
                        @if(str_starts_with($selectedStudioPresetId, 'custom_'))
                            <button type="button"
                                    wire:click="deleteSelectedCustomPreset"
                                    wire:confirm="Eigenes Studio-Preset löschen?"
                                    class="text-[10px] font-bold text-red-500 hover:underline cursor-pointer">
                                Preset löschen
                            </button>
                        @endif
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Material- / Studio-Preset</label>
                        <select wire:model.live="selectedStudioPresetId"
                                wire:change="applyStudioPreset($event.target.value)"
                                class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white">
                            @foreach($this->studioPresets as $sp)
                                <option value="{{ $sp['id'] }}">{{ !empty($sp['is_custom']) ? '★ ' : '' }}{{ $sp['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Equipment</label>
                            <select wire:model.live="laserParams.equipment_key"
                                    wire:change="saveMetadataOnly(true)"
                                    class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-2 text-xs font-bold text-gray-900 dark:text-white">
                                @foreach($this->equipmentModes as $eqKey => $eqMeta)
                                    <option value="{{ $eqKey }}">{{ $eqMeta['label'] ?? $eqKey }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Fokus (mm)</label>
                            <div class="flex gap-1.5">
                                <input type="number" step="0.1" min="0" max="180"
                                       wire:model.blur="laserParams.focus_mm"
                                       wire:change="saveMetadataOnly(true)"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white" />
                                <button type="button"
                                        wire:click="measureAutoFocus"
                                        title="Auto-Fokus am xTool F2 Ultra UV messen"
                                        class="px-2.5 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] text-[10px] font-black text-[var(--theme-color)] shrink-0 cursor-pointer">
                                    Auto
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Leistung (%)</label>
                            <input type="number" min="1" max="100" wire:model.blur="laserParams.power_percent" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-mono font-bold text-gray-900 dark:text-white" />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Speed (mm/s)</label>
                            <input type="number" min="5" max="4000" wire:model.blur="laserParams.speed_mms" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-mono font-bold text-gray-900 dark:text-white" />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">DPI</label>
                            <input type="number" step="50" min="100" max="2540" wire:model.blur="laserParams.dpi" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-mono font-bold text-gray-900 dark:text-white" />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Punkt-Dauer (µs)</label>
                            <input type="number" min="1" max="3000" wire:model.blur="laserParams.dot_duration_us" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-mono font-bold text-gray-900 dark:text-white" />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Frequenz (kHz)</label>
                            <input type="number" min="40" max="200" wire:model.blur="laserParams.frequency_khz" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-mono font-bold text-gray-900 dark:text-white" />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Durchgänge</label>
                            <input type="number" min="1" max="20" wire:model.blur="laserParams.passes" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-mono font-bold text-gray-900 dark:text-white" />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Bitmap-Modus</label>
                            <select wire:model.live="laserParams.bitmap_mode" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-bold text-gray-900 dark:text-white">
                                <option value="Schwellenwert">Schwellenwert (Scharf)</option>
                                <option value="Jarvis">Jarvis (Foto)</option>
                                <option value="Stucki">Stucki</option>
                                <option value="Graustufen">Graustufen</option>
                                <option value="Flächenfüllung (Vektor)">Flächenfüllung</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Scan-Richtung</label>
                            <select wire:model.live="laserParams.engrave_mode" wire:change="saveMetadataOnly(true)" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 font-bold text-gray-900 dark:text-white">
                                <option value="Bidirektional">Bidirektional</option>
                                <option value="Eindirektional">Eindirektional</option>
                            </select>
                        </div>
                    </div>

                    {{-- EIGENES MATERIAL-PRESET SPEICHERN --}}
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-800 flex gap-2">
                        <input type="text"
                               wire:model="newCustomPresetName"
                               placeholder="Aktuelle Werte als eigenes Preset merken..."
                               class="flex-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-1.5 text-xs text-gray-900 dark:text-white outline-none" />
                        <button type="button"
                                wire:click="saveCurrentAsStudioPreset"
                                class="px-3 py-1.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-950 text-[10px] font-black uppercase tracking-wider shrink-0 cursor-pointer">
                            + Preset merken
                        </button>
                    </div>
                </div>

                {{-- KARTE 3: DATEIUPLOAD-VERWALTUNG & LASER-FOTO-STUDIO (EXAKT WIE IN BESTELLUNG SCHRITT 1) --}}
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white">
                            <span>Dateiupload-Verwaltung</span>
                            <span title="Alle im Produktkonfigurator hochgeladenen Bilder erscheinen sofort hier. Über das Zahnrad öffnest du das 60-FPS Laser-Foto-Studio (Graustufen, Invertieren, Kontrast, Lichter-Schutz & Jarvis-Raster)."
                                  class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 inline-flex items-center justify-center text-[9px] font-black cursor-help shrink-0">!</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-md bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[10px] font-mono font-bold text-gray-600 dark:text-gray-300">
                            {{ count($this->projectUploadedFiles) }} {{ count($this->projectUploadedFiles) === 1 ? 'Datei' : 'Dateien' }}
                        </span>
                    </div>

                    @if(empty($this->projectUploadedFiles))
                        <div class="p-3.5 rounded-xl border border-dashed border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/50 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">
                            Noch keine Bilddatei im Projekt hinterlegt (+ Bild im Konfigurator nutzen)
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach($this->projectUploadedFiles as $cf)
                                <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        @if($cf['is_image'])
                                            <button type="button"
                                                    @click.stop="openStudioLightbox(@js($cf['preview_url']), @js($cf['label']))"
                                                    title="Großansicht in Lightbox öffnen"
                                                    class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] overflow-hidden shrink-0 cursor-zoom-in transition-colors">
                                                <img src="{{ $cf['preview_url'] }}" alt="{{ $cf['label'] }}" class="w-full h-full object-cover" loading="lazy">
                                            </button>
                                        @else
                                            <span class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[10px] font-black uppercase text-[var(--theme-color)] shrink-0">
                                                {{ strtoupper($cf['ext']) }}
                                            </span>
                                        @endif

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <button type="button"
                                                        @if($cf['is_image']) @click.stop="openStudioLightbox(@js($cf['preview_url']), @js($cf['label']))" @endif
                                                        class="text-xs font-bold text-gray-900 dark:text-white hover:text-[var(--theme-color)] truncate max-w-[170px] sm:max-w-[230px] text-left {{ $cf['is_image'] ? 'cursor-pointer' : 'cursor-default' }}">
                                                    {{ $cf['label'] }}
                                                </button>

                                                @if($cf['has_untouched_original'])
                                                    <span class="px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider shrink-0">
                                                        Original
                                                    </span>
                                                @endif

                                                @if($cf['is_laser_optimized'])
                                                    <span class="px-1.5 py-0.5 rounded-md bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-40)] text-[9px] font-black uppercase tracking-wider shrink-0">
                                                        Laser-optimiert
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($cf['is_raster'])
                                            <button type="button"
                                                    data-open-photo-studio
                                                    @click.stop
                                                    wire:click="openLaserPhotoStudio('{{ $cf['raw_key'] }}')"
                                                    title="Bild für Laser nachbearbeiten (Graustufen, Kontrast & Raster)"
                                                    aria-label="Bild für Laser nachbearbeiten"
                                                    class="p-2 rounded-lg border transition-colors cursor-pointer {{ $cf['is_laser_optimized']
                                                        ? 'bg-[var(--theme-color-15)] border-[var(--theme-color)] text-[var(--theme-color)] hover:bg-[var(--theme-color)] hover:text-gray-950'
                                                        : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)]' }}">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                            </button>
                                        @endif

                                        <a href="{{ $cf['download_url'] }}"
                                           download="{{ $cf['download_name'] }}"
                                           @click.stop
                                           title="Originaldatei herunterladen"
                                           class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-[var(--theme-color)] hover:text-gray-950 hover:border-[var(--theme-color)] transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                        </a>

                                        <button type="button"
                                                @click.stop
                                                wire:click="removeStudioUploadedFile('{{ $cf['raw_key'] }}')"
                                                title="Datei entfernen"
                                                aria-label="Datei entfernen"
                                                class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 hover:border-red-500/30 transition-colors cursor-pointer">
                                            <x-heroicon-m-trash class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- 60-FPS LASER-FOTO-STUDIO MODAL (1:1 AUS BESTELLUNG SCHRITT 1) --}}
    @include('livewire.shop.order.order-overview-partials.laser-photo-studio-modal')

    {{-- LIGHTBOX FÜR DATEIVORSCHAU IM SELBEN FENSTER --}}
    <div x-show="lightboxOpen"
         x-cloak
         @keydown.escape.window="lightboxOpen = false"
         @click.self="lightboxOpen = false"
         class="fixed inset-0 z-[150] bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="relative max-w-3xl w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl overflow-hidden shadow-2xl">
            <div class="px-5 py-3.5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between gap-3">
                <span class="text-xs font-bold text-gray-900 dark:text-white truncate" x-text="lightboxLabel"></span>
                <button type="button" @click="lightboxOpen = false" class="text-gray-500 hover:text-gray-900 dark:hover:text-white cursor-pointer text-sm font-bold">✕</button>
            </div>
            <div class="p-6 flex items-center justify-center bg-gray-100 dark:bg-gray-950 max-h-[78vh]">
                <img :src="lightboxUrl" :alt="lightboxLabel" class="max-h-[70vh] max-w-full object-contain rounded-xl">
            </div>
        </div>
    </div>

    {{-- MODAL: NEUES PROJEKT MIT EIGENEM MASS ODER SHOP-ROHLING --}}
    @if($showNewProjectModal)
        <div class="fixed inset-0 z-[130] bg-black/70 flex items-center justify-center p-4"
             wire:click.self="$set('showNewProjectModal', false)">
            <div class="max-w-md w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="font-serif font-bold text-lg text-gray-900 dark:text-white">Neues Laser-Projekt</h3>
                    <button type="button" wire:click="$set('showNewProjectModal', false)" class="text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                    <button type="button" wire:click="selectNewProjectPreset('card_86x54')" class="p-2.5 rounded-xl border text-left cursor-pointer {{ $newProjectPreset === 'card_86x54' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800' }}">
                        Karte (86×54 mm)
                    </button>
                    <button type="button" wire:click="selectNewProjectPreset('free_bed')" class="p-2.5 rounded-xl border text-left cursor-pointer {{ $newProjectPreset === 'free_bed' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800' }}">
                        Freies Bett (200×200 mm)
                    </button>
                    <button type="button" wire:click="selectNewProjectPreset('square_50x50')" class="p-2.5 rounded-xl border text-left cursor-pointer {{ $newProjectPreset === 'square_50x50' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800' }}">
                        Eigenes Maß (mm)
                    </button>
                    <button type="button" wire:click="selectNewProjectPreset('product_blank')" class="p-2.5 rounded-xl border text-left cursor-pointer {{ $newProjectPreset === 'product_blank' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800' }}">
                        Shop-Rohling wählen
                    </button>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Projekttitel</label>
                    <input type="text" wire:model="newProjectTitle" placeholder="z.B. Instagram QR-Karte Gold" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none" />
                </div>

                @if($newProjectPreset === 'product_blank')
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Shop-Rohling</label>
                        <select wire:model="newProjectProductId" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white">
                            <option value="">Bitte Produkt wählen...</option>
                            @foreach($this->physicalProducts as $pp)
                                <option value="{{ $pp->id }}">{{ $pp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Breite (mm)</label>
                            <input type="number" step="0.5" min="5" max="200" wire:model="newProjectWidthMm" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Höhe (mm)</label>
                            <input type="number" step="0.5" min="5" max="200" wire:model="newProjectHeightMm" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white" />
                        </div>
                    </div>
                @endif

                <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-700 dark:text-gray-300 cursor-pointer">
                    <input type="checkbox" wire:model="newProjectTwoSided" class="rounded border-gray-300 text-[var(--theme-color)]" />
                    <span>Beidseitiges Projekt (Vorder- &amp; Rückseite)</span>
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showNewProjectModal', false)" class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-bold cursor-pointer">Abbrechen</button>
                    <button type="button" wire:click="createProject" class="px-5 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 text-xs font-black uppercase tracking-wider cursor-pointer">Projekt starten</button>
                </div>
            </div>
        </div>
    @endif
</div>
