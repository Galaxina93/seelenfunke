<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="space-y-6 pb-20 animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300">

    {{-- HEADER ANALOG ZU PRODUKTE & VORLAGEN --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-hidden">
        <x-heroicon-o-adjustments-horizontal class="w-40 h-40 text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_20px_var(--theme-color)] shrink-0" />
        <div class="relative z-10 flex-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Produktkonfigurator</h1>
                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                    Zentrale Steuerung
                </span>
            </div>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm font-medium">
                Verwalte das Konfigurator-Anleitungsvideo, die animierten 3D-Bühnenhintergründe & Kaminfeuer-/Jahreszeiten-Videos sowie die gesamte Symbol- & Gravurmotiv-Bibliothek an einem zentralen Ort.
            </p>
        </div>
        <div class="relative z-10 flex flex-wrap gap-3">
            <a href="{{ route('admin.product-templates') }}" wire:navigate class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:text-[var(--theme-color)] px-5 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all flex items-center gap-2 border border-gray-200 dark:border-gray-700 shadow-xs">
                <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-[var(--theme-color)]" />
                Zu den Vorlagen
            </a>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 1. TUTORIAL-VIDEO VERWALTUNG (DRAG & DROP + 1-KLICK AUSTAUSCH)            --}}
    {{-- ========================================================================= --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 shadow-sm dark:shadow-2xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-gray-800">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-gray-900 dark:text-white font-serif font-bold text-xl tracking-wide flex items-center gap-2">
                        <x-heroicon-o-video-camera class="w-6 h-6 text-[var(--theme-color)]" />
                        Anleitungsvideo („Wie möchtest du starten?“)
                    </h2>
                    @if(!empty($tutorialConfig['enabled']))
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            Im Shop aktiv (Lazy-Load)
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-gray-500/10 text-gray-500 border border-gray-500/20">
                            Im Shop ausgeblendet
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Wird im Produkt unterhalb von „Vorlage nutzen / Selber Konfigurieren“ angezeigt und erst beim Klick auf „Tutorial ansehen“ geladen (0 Bytes Vorab-Traffic).
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button"
                        wire:click="toggleTutorialEnabled"
                        class="px-4 py-2.5 rounded-xl border text-xs font-bold transition-all flex items-center gap-2 cursor-pointer {{ !empty($tutorialConfig['enabled']) ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:border-[var(--theme-color)]' : 'bg-[var(--theme-color)] text-gray-900 border-[var(--theme-color)]' }}">
                    @if(!empty($tutorialConfig['enabled']))
                        <x-heroicon-o-eye-slash class="w-4 h-4 text-gray-400" />
                        <span>Button im Shop ausblenden</span>
                    @else
                        <x-heroicon-o-eye class="w-4 h-4" />
                        <span>Button im Shop einblenden</span>
                    @endif
                </button>

                @if(!empty($tutorialConfig['is_custom']) && !empty($tutorialConfig['has_default']))
                    <button type="button"
                            wire:click="resetTutorialToDefault"
                            wire:confirm="Möchtest du das eigene Video entfernen und wieder das Standard-Tutorial-Video aktivieren?"
                            class="px-4 py-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 text-xs font-bold transition-all flex items-center gap-2 cursor-pointer">
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        <span>Standard-Video wiederherstellen</span>
                    </button>
                @endif
            </div>
        </div>

        @if(session()->has('tutorial_success'))
            <div class="mt-4 px-4 py-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-bold flex items-center gap-2">
                <x-heroicon-m-check-circle class="w-4 h-4 shrink-0 text-emerald-500" />
                <span>{{ session('tutorial_success') }}</span>
            </div>
        @endif

        @error('tutorialVideoUpload')
            <div class="mt-4 px-4 py-3 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-600 dark:text-red-400 text-xs font-bold flex items-center gap-2">
                <x-heroicon-m-exclamation-triangle class="w-4 h-4 shrink-0" />
                <span>{{ $message }}</span>
            </div>
        @enderror

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">
            {{-- LINKE SPALTE: AKTUELLES VIDEO & VORSCHAU --}}
            <div class="lg:col-span-7 flex flex-col">
                <div class="rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-800 bg-[#FAF8F5] dark:bg-gray-950 shadow-inner relative aspect-video flex items-center justify-center">
                    @if(!empty($tutorialConfig['exists']) && !empty($tutorialConfig['url']))
                        <video key="{{ $tutorialConfig['url'] }}"
                               src="{{ $tutorialConfig['url'] }}"
                               controls
                               playsinline
                               preload="metadata"
                               class="w-full h-full object-contain bg-[#FAF8F5] dark:bg-gray-950">
                        </video>
                    @else
                        <div class="text-center p-8 text-gray-500">
                            <x-heroicon-o-video-camera-slash class="w-12 h-12 mx-auto mb-2 text-gray-400" />
                            <p class="text-xs font-bold">Noch kein Tutorial-Video vorhanden.</p>
                        </div>
                    @endif
                </div>

                <div class="mt-3 px-4 py-3 rounded-2xl bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider {{ !empty($tutorialConfig['is_custom']) ? 'bg-[var(--theme-color-20)] text-[var(--theme-color)]' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400' }}">
                            {{ !empty($tutorialConfig['is_custom']) ? 'Eigenes Video' : 'Standard-Tutorial' }}
                        </span>
                        <span class="font-mono text-[11px] text-gray-600 dark:text-gray-300 truncate" title="{{ $tutorialConfig['storage_path'] ?? '' }}">
                            {{ $tutorialConfig['storage_path'] ?? '' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-gray-500 shrink-0">
                        @if(!empty($tutorialConfig['size_formatted']))
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $tutorialConfig['size_formatted'] }}</span>
                        @endif
                        @if(!empty($tutorialConfig['updated_at']))
                            <span>Stand: {{ $tutorialConfig['updated_at'] }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- RECHTE SPALTE: DRAG & DROP VIDEO-AUSTAUSCH --}}
            <div class="lg:col-span-5 flex flex-col"
                 x-data="{
                    isDragging: false,
                    isUploading: false,
                    progress: 0,
                    handleDrop(e) {
                        this.isDragging = false;
                        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                            this.$refs.videoInput.files = e.dataTransfer.files;
                            this.$refs.videoInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                 }"
                 x-on:livewire-upload-start="isUploading = true; progress = 0"
                 x-on:livewire-upload-finish="isUploading = false; progress = 100"
                 x-on:livewire-upload-error="isUploading = false"
                 x-on:livewire-upload-progress="progress = $event.detail.progress">

                <div @dragover.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="handleDrop($event)"
                     @click="$refs.videoInput.click()"
                     :class="isDragging
                        ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] scale-[1.01]'
                        : 'border-gray-300 dark:border-gray-700 hover:border-[var(--theme-color)] bg-gray-50/70 dark:bg-gray-950/50 hover:bg-[var(--theme-color-5)]'"
                     class="flex-1 rounded-3xl border-2 border-dashed p-6 sm:p-8 flex flex-col items-center justify-center text-center transition-all cursor-pointer select-none min-h-[240px]">

                    <input x-ref="videoInput"
                           type="file"
                           wire:model="tutorialVideoUpload"
                           accept="video/mp4,video/webm,video/quicktime"
                           class="hidden" />

                    <div class="w-16 h-16 rounded-2xl bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] mb-4 shadow-sm">
                        <x-heroicon-o-arrow-up-tray class="w-8 h-8" />
                    </div>

                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                        Tutorial-Video per Klick oder Drag & Drop austauschen
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-xs leading-relaxed">
                        Ziehe eine neue <strong class="text-gray-700 dark:text-gray-200">.mp4</strong> oder <strong class="text-gray-700 dark:text-gray-200">.webm</strong> Datei hierher. Das Video wird sofort unter <code class="text-[10px] px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-800">produkte/configurator/tutorial</code> gespeichert.
                    </p>

                    <div class="mt-5 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-[10px] uppercase tracking-widest shadow-sm hover:opacity-95 transition-opacity">
                        <x-heroicon-m-film class="w-4 h-4" />
                        <span>Videodatei auswählen</span>
                    </div>

                    {{-- Live Upload-Fortschritt --}}
                    <div x-show="isUploading" x-cloak class="w-full mt-5 space-y-1.5">
                        <div class="flex justify-between text-[11px] font-bold text-[var(--theme-color)]">
                            <span>Video wird hochgeladen & aktiviert...</span>
                            <span x-text="progress + '%'"></span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                            <div class="h-full bg-[var(--theme-color)] transition-all duration-150" :style="'width: ' + progress + '%'"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 2. KONFIGURATOR-BÜHNENHINTERGRÜNDE & ANIMIERTE THEMEN-VIDEOS (.MP4 / .JPG) --}}
    {{-- ========================================================================= --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 shadow-sm dark:shadow-2xl space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-gray-800">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-gray-900 dark:text-white font-serif font-bold text-xl tracking-wide flex items-center gap-2">
                        <x-heroicon-o-film class="w-6 h-6 text-[var(--theme-color)]" />
                        Konfigurator-Hintergrundvideos & 3D-Bühnenbilder (Jahreszeiten & Feiertage)
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        Aktuell im Konfigurator: {{ $currentActiveTheme['theme']['icon'] ?? '✨' }} {{ $currentActiveTheme['theme']['label'] ?? 'Manufaktur' }} ({{ $currentActiveTheme['event_name'] ?? 'Automatisch' }})
                    </span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Diese kurzen, nahtlos geloopten <strong>.mp4 Videos</strong> (z. B. brodelndes Kaminfeuer, Schneefall, Herbstlaub) und hochauflösenden <strong>3D-Bühnenbilder (.jpg)</strong> laufen automatisch im Hintergrund der 2D- & 3D-Produktkonfiguration ab. Du kannst hier jede Themen-Welt live betrachten, per Klick sofort im Shop aktivieren oder eigene Dateien hinterlegen.
                </p>
            </div>

            @if(!empty($manualAnimKey))
                <button type="button"
                        wire:click="pinThemeAnimation('{{ $manualAnimKey }}')"
                        class="px-4 py-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 text-xs font-bold transition-all flex items-center gap-2 cursor-pointer shrink-0">
                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                    <span>Auf Kalender-Automatik zurücksetzen</span>
                </button>
            @endif
        </div>

        @if(session()->has('themes_success'))
            <div class="px-4 py-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-bold flex items-center gap-2">
                <x-heroicon-m-check-circle class="w-4 h-4 shrink-0 text-emerald-500" />
                <span>{{ session('themes_success') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($backgroundThemes ?? [] as $tKey => $theme)
                @php
                    $isCurrentlyActive = ($currentActiveTheme['theme_key'] ?? 'standard') === $tKey;
                    $isManuallyPinned = ($manualAnimKey ?? '') === $tKey;
                    $vDisk = storage_path('app/public/' . $theme['video']);
                    $iDisk = storage_path('app/public/' . $theme['image']);
                    $vSizeKb = is_file($vDisk) ? round(filesize($vDisk) / 1024, 1) : 0;
                    $iSizeKb = is_file($iDisk) ? round(filesize($iDisk) / 1024, 1) : 0;
                    $vUrl = asset('storage/' . $theme['video']) . (is_file($vDisk) ? '?v=' . filemtime($vDisk) : '');
                    $iUrl = asset('storage/' . $theme['image']) . (is_file($iDisk) ? '?v=' . filemtime($iDisk) : '');
                @endphp
                <div wire:key="cfg-theme-card-{{ $tKey }}"
                     class="rounded-3xl border p-4 flex flex-col justify-between transition-all {{ $isCurrentlyActive ? 'bg-[var(--theme-color-10)] border-2 border-[var(--theme-color)] shadow-[0_0_25px_var(--theme-color-20)]' : 'bg-gray-50/70 dark:bg-gray-950/60 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">

                    <div>
                        {{-- Kopfzeile --}}
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-xl leading-none">{{ $theme['icon'] }}</span>
                                <div class="min-w-0">
                                    <h3 class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $theme['label'] }}</h3>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ $theme['holiday'] }}</p>
                                </div>
                            </div>
                            @if($isCurrentlyActive)
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-[var(--theme-color)] text-gray-900 shrink-0">
                                    {{ $isManuallyPinned ? 'Fixiert' : 'Aktiv' }}
                                </span>
                            @endif
                        </div>

                        {{-- Live Video-Bühnenvorschau (mit Poster = 3D Hintergrundbild) --}}
                        <div class="relative aspect-square rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-800 bg-gray-950 mb-3 group/vid">
                            <video key="{{ $vUrl }}"
                                   src="{{ $vUrl }}"
                                   poster="{{ $iUrl }}"
                                   x-init="$el.muted = true; $el.defaultMuted = true; $el.play().catch(() => {})"
                                   autoplay
                                   loop
                                   muted
                                   playsinline
                                   class="w-full h-full object-cover"></video>
                            <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between gap-1.5 px-2.5 py-1 rounded-xl bg-gray-950/80 backdrop-blur-md border border-white/10 text-[9px] font-mono text-gray-200">
                                <span class="truncate">🎬 {{ basename($theme['video']) }} ({{ $vSizeKb }} KB)</span>
                                <span class="text-amber-400 shrink-0">🖼 {{ $iSizeKb }} KB</span>
                            </div>
                        </div>
                    </div>

                    {{-- Aktionen: Aktivieren + Video/Bild ersetzen --}}
                    <div class="space-y-2 pt-2 border-t border-gray-200/70 dark:border-gray-800">
                        <button type="button"
                                wire:click="pinThemeAnimation('{{ $tKey }}')"
                                class="w-full py-2 px-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $isCurrentlyActive ? 'bg-[var(--theme-color)] text-gray-900 shadow-xs' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)]' }}">
                            @if($isManuallyPinned)
                                <span>✓ Als aktive Shop-Bühne fixiert (Lösen)</span>
                            @elseif($isCurrentlyActive)
                                <span>✓ Aktuell aktiv (Per Klick fixieren)</span>
                            @else
                                <span>Jetzt im Konfigurator aktivieren</span>
                            @endif
                        </button>

                        <div class="grid grid-cols-2 gap-1.5">
                            <label class="cursor-pointer py-1.5 px-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] text-[9px] font-bold text-center text-gray-600 dark:text-gray-400 hover:text-[var(--theme-color)] transition-colors">
                                Video (.mp4) ändern
                                <input type="file" wire:model.live="themeVideoUploads.{{ $tKey }}" accept="video/mp4,video/webm" class="hidden">
                            </label>
                            <label class="cursor-pointer py-1.5 px-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] text-[9px] font-bold text-center text-gray-600 dark:text-gray-400 hover:text-[var(--theme-color)] transition-colors">
                                Bild (.jpg) ändern
                                <input type="file" wire:model.live="themeImageUploads.{{ $tKey }}" accept="image/jpeg,image/png,image/webp" class="hidden">
                            </label>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 3. SYMBOL-BIBLIOTHEK VERWALTUNG (MINIMALISTISCH, KLAR & ÜBERSICHTLICH)    --}}
    {{-- ========================================================================= --}}
    @php
        $currentSymbolLayout = in_array($layoutMode ?? 'medium', ['small', 'medium', 'large', 'table'], true) ? $layoutMode : 'medium';
    @endphp
    <div x-data="{
            syncSymbolLayout() {
                try {
                    const saved = localStorage.getItem('admin_configurator_symbols_layout_mode');
                    if (saved && ['small', 'medium', 'large', 'table'].includes(saved) && saved !== '{{ $currentSymbolLayout }}') {
                        $wire.setLayoutMode(saved);
                    } else {
                        localStorage.setItem('admin_configurator_symbols_layout_mode', '{{ $currentSymbolLayout }}');
                    }
                } catch (e) {}
            }
         }"
         x-init="syncSymbolLayout()"
         class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 shadow-sm dark:shadow-2xl space-y-6">

        {{-- KOPFZEILE & SCHNELLSTATISTIK --}}
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-gray-800">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-gray-900 dark:text-white font-serif font-bold text-xl tracking-wide flex items-center gap-2">
                        <x-heroicon-o-sparkles class="w-6 h-6 text-[var(--theme-color)]" />
                        Symbol-Bibliothek Verwaltung
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                        {{ $symbolStats['active'] ?? 0 }} aktiv • {{ $symbolStats['archived'] ?? 0 }} archiviert
                    </span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Neue SVG-Motive per Drag & Drop hinzufügen, kategorisieren, umbenennen oder archivieren (archivierte Motive bleiben für bestehende Bestellungen erhalten).
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                {{-- ANSICHTSAUSWAHL (Klein / Mittel / Groß / Tabelle) analog zu "Deine Kollektion" --}}
                <div class="inline-flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800" role="group" aria-label="Ansichtsauswahl">
                    {{-- Klein --}}
                    <button type="button"
                            wire:click="setLayoutMode('small')"
                            onclick="try { localStorage.setItem('admin_configurator_symbols_layout_mode', 'small'); } catch (e) {}"
                            class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer {{ $currentSymbolLayout === 'small' ? 'bg-[var(--theme-color)] text-gray-900 shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                            title="Kleine Kachelansicht">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <rect x="2" y="2" width="3" height="3" rx="0.6" />
                            <rect x="6.6" y="2" width="3" height="3" rx="0.6" />
                            <rect x="11.3" y="2" width="3" height="3" rx="0.6" />
                            <rect x="16" y="2" width="2" height="3" rx="0.6" />
                            <rect x="2" y="6.6" width="3" height="3" rx="0.6" />
                            <rect x="6.6" y="6.6" width="3" height="3" rx="0.6" />
                            <rect x="11.3" y="6.6" width="3" height="3" rx="0.6" />
                            <rect x="16" y="6.6" width="2" height="3" rx="0.6" />
                            <rect x="2" y="11.3" width="3" height="3" rx="0.6" />
                            <rect x="6.6" y="11.3" width="3" height="3" rx="0.6" />
                            <rect x="11.3" y="11.3" width="3" height="3" rx="0.6" />
                            <rect x="16" y="11.3" width="2" height="3" rx="0.6" />
                            <rect x="2" y="16" width="3" height="2" rx="0.6" />
                            <rect x="6.6" y="16" width="3" height="2" rx="0.6" />
                            <rect x="11.3" y="16" width="3" height="2" rx="0.6" />
                            <rect x="16" y="16" width="2" height="2" rx="0.6" />
                        </svg>
                        <span>Klein</span>
                    </button>

                    {{-- Mittel --}}
                    <button type="button"
                            wire:click="setLayoutMode('medium')"
                            onclick="try { localStorage.setItem('admin_configurator_symbols_layout_mode', 'medium'); } catch (e) {}"
                            class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer {{ $currentSymbolLayout === 'medium' ? 'bg-[var(--theme-color)] text-gray-900 shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                            title="Mittlere Kachelansicht">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="4.5" height="4.5" rx="1" />
                            <rect x="9.75" y="3" width="4.5" height="4.5" rx="1" />
                            <rect x="16.5" y="3" width="4.5" height="4.5" rx="1" />
                            <rect x="3" y="9.75" width="4.5" height="4.5" rx="1" />
                            <rect x="9.75" y="9.75" width="4.5" height="4.5" rx="1" />
                            <rect x="16.5" y="9.75" width="4.5" height="4.5" rx="1" />
                            <rect x="3" y="16.5" width="4.5" height="4.5" rx="1" />
                            <rect x="9.75" y="16.5" width="4.5" height="4.5" rx="1" />
                            <rect x="16.5" y="16.5" width="4.5" height="4.5" rx="1" />
                        </svg>
                        <span>Mittel</span>
                    </button>

                    {{-- Groß --}}
                    <button type="button"
                            wire:click="setLayoutMode('large')"
                            onclick="try { localStorage.setItem('admin_configurator_symbols_layout_mode', 'large'); } catch (e) {}"
                            class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer {{ $currentSymbolLayout === 'large' ? 'bg-[var(--theme-color)] text-gray-900 shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                            title="Große Kachelansicht">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7" rx="1.5" />
                            <rect x="14" y="3" width="7" height="7" rx="1.5" />
                            <rect x="3" y="14" width="7" height="7" rx="1.5" />
                            <rect x="14" y="14" width="7" height="7" rx="1.5" />
                        </svg>
                        <span>Groß</span>
                    </button>

                    {{-- Tabelle --}}
                    <button type="button"
                            wire:click="setLayoutMode('table')"
                            onclick="try { localStorage.setItem('admin_configurator_symbols_layout_mode', 'table'); } catch (e) {}"
                            class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer {{ $currentSymbolLayout === 'table' ? 'bg-[var(--theme-color)] text-gray-900 shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                            title="Tabellenansicht">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <span>Tabelle</span>
                    </button>
                </div>

                {{-- Kontrast-Vorschau-Schalter (Hell vs. Dunkel / Seelenanhänger) --}}
                <div class="inline-flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-[11px] font-bold">
                    <button type="button"
                            wire:click="$set('previewSurface', 'light')"
                            class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer {{ $previewSurface === 'light' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        <x-heroicon-m-sun class="w-3.5 h-3.5 text-amber-500" />
                        <span>Heller Grund</span>
                    </button>
                    <button type="button"
                            wire:click="$set('previewSurface', 'dark')"
                            class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer {{ $previewSurface === 'dark' ? 'bg-gray-800 text-white shadow-xs' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        <x-heroicon-m-moon class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                        <span>Dunkler Grund (Laser)</span>
                    </button>
                </div>

                {{-- Kategorien verwalten Toggle --}}
                <button type="button"
                        wire:click="$toggle('showCategoryManager')"
                        class="px-4 py-2 rounded-xl border text-xs font-bold transition-all flex items-center gap-2 cursor-pointer {{ $showCategoryManager ? 'bg-[var(--theme-color)] text-gray-900 border-[var(--theme-color)]' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:border-[var(--theme-color)]' }}">
                    <x-heroicon-o-folder-plus class="w-4 h-4" />
                    <span>Kategorien ({{ count($categories) }})</span>
                </button>
            </div>
        </div>

        @if(session()->has('symbols_success'))
            <div class="px-4 py-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-bold flex items-center gap-2">
                <x-heroicon-m-check-circle class="w-4 h-4 shrink-0 text-emerald-500" />
                <span>{{ session('symbols_success') }}</span>
            </div>
        @endif

        {{-- AUSKLAPPBARE KATEGORIEN-VERWALTUNG --}}
        @if($showCategoryManager)
            <div class="p-5 rounded-3xl bg-gray-50 dark:bg-gray-950/90 border border-gray-200 dark:border-gray-800 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Kategorien & Reihenfolge verwalten</h3>
                        <p class="text-[11px] text-gray-500">Bestimme die Tab-Reihenfolge im Produktkonfigurator, benenne Kategorien um oder lege neue an.</p>
                    </div>
                    <form wire:submit.prevent="createCategory" class="flex items-center gap-2">
                        <input type="text"
                               wire:model="newCategoryName"
                               placeholder="Neue Kategorie (z.B. Sternzeichen)..."
                               class="px-3.5 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-[var(--theme-color)]" />
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-[10px] uppercase tracking-widest cursor-pointer hover:opacity-90 shrink-0">
                            + Anlegen
                        </button>
                    </form>
                </div>

                <div class="flex flex-wrap gap-2.5 pt-2">
                    @foreach($categories as $idx => $cat)
                        <div class="px-3 py-2 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center gap-2 text-xs shadow-2xs">
                            @if($renamingCategory === $cat)
                                <form wire:submit.prevent="saveRenameCategory" class="flex items-center gap-1.5">
                                    <input type="text"
                                           wire:model="renameCategoryValue"
                                           class="px-2 py-1 rounded-lg border border-[var(--theme-color)] bg-gray-50 dark:bg-gray-950 text-xs text-gray-900 dark:text-white w-32 focus:outline-none" />
                                    <button type="submit" class="p-1 text-emerald-500 hover:text-emerald-600 cursor-pointer" title="Speichern">
                                        <x-heroicon-m-check class="w-4 h-4" />
                                    </button>
                                    <button type="button" wire:click="cancelRenameCategory" class="p-1 text-gray-400 hover:text-gray-600 cursor-pointer" title="Abbrechen">
                                        <x-heroicon-m-x-mark class="w-4 h-4" />
                                    </button>
                                </form>
                            @else
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ $cat }}</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 font-mono">
                                    {{ $categoryCounts[$cat] ?? 0 }}
                                </span>

                                <div class="flex items-center gap-0.5 ml-1 border-l border-gray-200 dark:border-gray-800 pl-1.5">
                                    @if($idx > 0)
                                        <button type="button" wire:click="moveCategory('{{ $cat }}', 'up')" class="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer" title="Nach vorne">
                                            <x-heroicon-m-chevron-left class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                    @if($idx < count($categories) - 1)
                                        <button type="button" wire:click="moveCategory('{{ $cat }}', 'down')" class="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer" title="Nach hinten">
                                            <x-heroicon-m-chevron-right class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                    <button type="button" wire:click="startRenameCategory('{{ $cat }}')" class="p-1 text-gray-400 hover:text-[var(--theme-color)] cursor-pointer" title="Umbenennen">
                                        <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                                    </button>
                                    @if($cat !== 'Allgemein')
                                        <button type="button"
                                                wire:click="removeCategory('{{ $cat }}')"
                                                wire:confirm="Kategorie „{{ $cat }}“ entfernen? Enthaltene Symbole werden automatisch nach „Allgemein“ verschoben."
                                                class="p-1 text-gray-400 hover:text-red-500 cursor-pointer"
                                                title="Kategorie löschen">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- KOMPAKTE DRAG & DROP SVG-UPLOAD-LEISTE --}}
        <div x-data="{
                draggingSvg: false,
                handleSvgDrop(e) {
                    this.draggingSvg = false;
                    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                        this.$refs.svgInput.files = e.dataTransfer.files;
                        this.$refs.svgInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
             }"
             @dragover.prevent="draggingSvg = true"
             @dragleave.prevent="draggingSvg = false"
             @drop.prevent="handleSvgDrop($event)"
             :class="draggingSvg ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60'"
             class="rounded-3xl border-2 border-dashed p-5 transition-all">

            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 cursor-pointer" @click="$refs.svgInput.click()">
                    <div class="w-12 h-12 rounded-2xl bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shrink-0">
                        <x-heroicon-o-plus class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                            Neue SVG-Symbole hinzufügen (Drag & Drop oder Klick)
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Einzelne oder mehrere <code class="text-[10px] font-mono">.svg</code> Vektordateien ablegen, Kategorie wählen und direkt speichern.
                        </p>
                    </div>
                    <input x-ref="svgInput"
                           type="file"
                           wire:model="newSymbolFiles"
                           accept=".svg,image/svg+xml"
                           multiple
                           class="hidden" />
                </div>

                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
                    <div class="w-full sm:w-44">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Ziel-Kategorie</label>
                        <select wire:model="uploadCategory" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-bold text-gray-900 dark:text-white focus:outline-none focus:border-[var(--theme-color)]">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if(count($newSymbolFiles) <= 1)
                        <div class="w-full sm:w-48">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Anzeigename (optional)</label>
                            <input type="text"
                                   wire:model="uploadCustomName"
                                   placeholder="Automatisch aus Datei..."
                                   class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-[var(--theme-color)]" />
                        </div>
                    @endif

                    <div class="pt-4 sm:pt-4 flex items-center gap-2 w-full sm:w-auto">
                        <button type="button"
                                @click="$refs.svgInput.click()"
                                class="px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 hover:border-[var(--theme-color)] transition-all cursor-pointer shrink-0">
                            Dateien wählen
                        </button>

                        @if(count($newSymbolFiles) > 0)
                            <button type="button"
                                    wire:click="saveUploadedSymbols"
                                    class="px-5 py-2.5 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-[10px] uppercase tracking-widest shadow-sm hover:opacity-95 transition-all cursor-pointer shrink-0 flex items-center gap-1.5">
                                <x-heroicon-m-arrow-up-tray class="w-4 h-4" />
                                <span>{{ count($newSymbolFiles) }} {{ count($newSymbolFiles) === 1 ? 'Symbol speichern' : 'Symbole speichern' }}</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            @error('newSymbolFiles')
                <p class="text-xs text-red-500 font-bold mt-2">{{ $message }}</p>
            @enderror
            @error('newSymbolFiles.*')
                <p class="text-xs text-red-500 font-bold mt-2">{{ $message }}</p>
            @enderror

            {{-- Vorschau der ausgewählten Upload-Dateien --}}
            @if(count($newSymbolFiles) > 0)
                <div class="mt-4 pt-3 border-t border-gray-200/70 dark:border-gray-800 flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-[var(--theme-color)] mr-1">Bereit zum Speichern:</span>
                    @foreach($newSymbolFiles as $idx => $stagedFile)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200">
                            <x-heroicon-o-document class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                            <span>{{ $stagedFile->getClientOriginalName() }}</span>
                            <button type="button" wire:click="removeStagedFile({{ $idx }})" class="text-gray-400 hover:text-red-500 ml-1 cursor-pointer">✕</button>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- FILTER-LEISTE: KATEGORIEN, STATUS & SUCHE --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            {{-- Kategorie-Tabs --}}
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 custom-scrollbar">
                <button type="button"
                        wire:click="$set('selectedCategory', 'all')"
                        class="px-3.5 py-1.5 rounded-full text-[11px] font-bold whitespace-nowrap transition-all cursor-pointer border {{ $selectedCategory === 'all' ? 'bg-[var(--theme-color)] text-gray-900 border-[var(--theme-color)] shadow-xs' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                    Alle ({{ $symbolStats['total'] ?? 0 }})
                </button>
                @foreach($categories as $cat)
                    <button type="button"
                            wire:click="$set('selectedCategory', '{{ $cat }}')"
                            class="px-3.5 py-1.5 rounded-full text-[11px] font-bold whitespace-nowrap transition-all cursor-pointer border {{ $selectedCategory === $cat ? 'bg-[var(--theme-color)] text-gray-900 border-[var(--theme-color)] shadow-xs' : 'bg-gray-100 dark:bg-gray-800/80 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                        {{ $cat }} ({{ $categoryCounts[$cat] ?? 0 }})
                    </button>
                @endforeach
            </div>

            {{-- Status-Filter & Suche --}}
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5 shrink-0">
                <div class="inline-flex rounded-xl bg-gray-100 dark:bg-gray-950 p-1 border border-gray-200 dark:border-gray-800 text-[11px] font-bold">
                    <button type="button"
                            wire:click="$set('statusFilter', 'all')"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        Alle
                    </button>
                    <button type="button"
                            wire:click="$set('statusFilter', 'active')"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer {{ $statusFilter === 'active' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-2xs' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        Aktiv ({{ $symbolStats['active'] ?? 0 }})
                    </button>
                    <button type="button"
                            wire:click="$set('statusFilter', 'archived')"
                            class="px-2.5 py-1 rounded-lg transition-all cursor-pointer {{ $statusFilter === 'archived' ? 'bg-white dark:bg-gray-800 text-amber-600 dark:text-amber-400 shadow-2xs' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        Archiviert ({{ $symbolStats['archived'] ?? 0 }})
                    </button>
                </div>

                <div class="relative w-full sm:w-56">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input type="text"
                           wire:model.live.debounce.200ms="search"
                           placeholder="Symbol suchen..."
                           class="w-full pl-9 pr-8 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-white focus:outline-none focus:border-[var(--theme-color)]" />
                    @if($search !== '')
                        <button type="button" wire:click="$set('search', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs cursor-pointer">✕</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- SYMBOL-ANSICHTEN (Klein / Mittel / Groß / Tabelle) --}}
        @if(count($symbols) > 0)
            @if($currentSymbolLayout === 'table')
                {{-- ===================================================================== --}}
                {{-- TABELLENANSICHT                                                       --}}
                {{-- ===================================================================== --}}
                <div wire:key="symbols-layout-table" class="rounded-3xl border border-gray-200 dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-900/60 shadow-2xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/80 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                    <th class="py-3.5 pl-5 pr-3 w-16">Motiv</th>
                                    <th class="py-3.5 px-3">Anzeigename</th>
                                    <th class="py-3.5 px-3 w-48">Kategorie</th>
                                    <th class="py-3.5 px-3 hidden md:table-cell">Dateiname</th>
                                    <th class="py-3.5 px-3 hidden sm:table-cell w-24">Größe</th>
                                    <th class="py-3.5 px-3 w-28">Status</th>
                                    <th class="py-3.5 pl-3 pr-5 text-right w-36">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80 text-xs">
                                @foreach($symbols as $sym)
                                    @php
                                        $isArchived = !empty($sym['is_archived']);
                                        $isEditing = $editingSymbolId === $sym['id'];
                                    @endphp
                                    <tr wire:key="sym-table-{{ $sym['id'] }}"
                                        class="transition-colors {{ $isArchived ? 'bg-gray-50/50 dark:bg-gray-950/30 opacity-70 hover:opacity-100' : 'hover:bg-gray-50/80 dark:hover:bg-gray-800/40' }}">
                                        {{-- Motiv Vorschau --}}
                                        <td class="py-2.5 pl-5 pr-3">
                                            <div class="w-11 h-11 rounded-xl border flex items-center justify-center p-2 shrink-0 {{ $previewSurface === 'dark' ? 'bg-gray-950 border-gray-800 shadow-inner' : 'bg-amber-50/40 dark:bg-gray-800/40 border-amber-200/50 dark:border-gray-700/60' }}">
                                                <img src="{{ $sym['url'] }}"
                                                     alt="{{ $sym['name'] }}"
                                                     loading="lazy"
                                                     style="{{ $previewSurface === 'dark' ? 'filter: brightness(0) invert(1) drop-shadow(0px 0px 2px rgba(255,255,255,0.45)); opacity: 0.92;' : '' }}"
                                                     class="w-full h-full object-contain pointer-events-none" />
                                            </div>
                                        </td>

                                        {{-- Name (oder Inline Edit) --}}
                                        <td class="py-2.5 px-3">
                                            @if($isEditing)
                                                <form wire:submit.prevent="saveEditSymbol" class="flex items-center gap-2 max-w-xs">
                                                    <input type="text"
                                                           wire:model="editSymbolName"
                                                           class="flex-1 px-2.5 py-1.5 rounded-lg border border-[var(--theme-color)] bg-white dark:bg-gray-950 text-xs font-bold text-gray-900 dark:text-white focus:outline-none" />
                                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-[var(--theme-color)] text-gray-900 font-black text-[10px] uppercase tracking-wider cursor-pointer">
                                                        OK
                                                    </button>
                                                    <button type="button" wire:click="cancelEditSymbol" class="px-2 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 text-[10px] font-bold cursor-pointer">
                                                        ✕
                                                    </button>
                                                </form>
                                            @else
                                                <div class="font-bold text-gray-900 dark:text-white">{{ $sym['name'] }}</div>
                                                <div class="text-[10px] text-gray-400 font-mono md:hidden truncate max-w-[160px]">{{ basename($sym['file']) }}</div>
                                            @endif
                                        </td>

                                        {{-- Kategorie (Schnellwechsel per Select) --}}
                                        <td class="py-2.5 px-3">
                                            <select wire:change="quickChangeCategory('{{ $sym['id'] }}', '{{ addslashes($sym['name']) }}', $event.target.value)"
                                                    class="px-2.5 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-[11px] font-bold text-gray-700 dark:text-gray-200 focus:outline-none focus:border-[var(--theme-color)] cursor-pointer">
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat }}" @selected($sym['category'] === $cat)>{{ $cat }}</option>
                                                @endforeach
                                            </select>
                                        </td>

                                        {{-- Dateiname --}}
                                        <td class="py-2.5 px-3 hidden md:table-cell font-mono text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ basename($sym['file']) }}
                                        </td>

                                        {{-- Größe --}}
                                        <td class="py-2.5 px-3 hidden sm:table-cell font-mono text-[11px] text-gray-400">
                                            {{ $sym['size_kb'] }} KB
                                        </td>

                                        {{-- Status --}}
                                        <td class="py-2.5 px-3">
                                            @if($isArchived)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                    Archiviert
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    Aktiv
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Aktionen --}}
                                        <td class="py-2.5 pl-3 pr-5 text-right">
                                            <div class="inline-flex items-center justify-end gap-1">
                                                <button type="button"
                                                        wire:click="startEditSymbol('{{ $sym['id'] }}', '{{ addslashes($sym['name']) }}', '{{ addslashes($sym['category']) }}')"
                                                        class="p-1.5 rounded-lg text-gray-400 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                                        title="Umbenennen">
                                                    <x-heroicon-o-pencil class="w-4 h-4" />
                                                </button>
                                                <button type="button"
                                                        wire:click="toggleArchiveSymbol('{{ $sym['id'] }}')"
                                                        class="p-1.5 rounded-lg transition-colors cursor-pointer {{ $isArchived ? 'text-amber-500 hover:text-emerald-500 hover:bg-emerald-500/10' : 'text-gray-400 hover:text-amber-500 hover:bg-amber-500/10' }}"
                                                        title="{{ $isArchived ? 'Wieder aktivieren (im Shop einblenden)' : 'Archivieren (im Shop ausblenden)' }}">
                                                    @if($isArchived)
                                                        <x-heroicon-o-eye class="w-4 h-4" />
                                                    @else
                                                        <x-heroicon-o-archive-box class="w-4 h-4" />
                                                    @endif
                                                </button>
                                                <button type="button"
                                                        wire:click="deleteSymbol('{{ $sym['id'] }}')"
                                                        wire:confirm="Symbol „{{ $sym['name'] }}“ endgültig löschen? (Tipp: Nutze „Archivieren“, falls das Symbol nur im Shop ausgeblendet werden soll.)"
                                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer"
                                                        title="Endgültig löschen">
                                                    <x-heroicon-o-trash class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                {{-- ===================================================================== --}}
                {{-- KACHELANSICHTEN: KLEIN / MITTEL / GROSS                               --}}
                {{-- ===================================================================== --}}
                @php
                    $gridClasses = match($currentSymbolLayout) {
                        'small' => 'grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-2.5',
                        'large' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5',
                        default => 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4',
                    };
                @endphp
                <div wire:key="symbols-layout-{{ $currentSymbolLayout }}" class="grid {{ $gridClasses }}">
                    @foreach($symbols as $sym)
                        @php
                            $isArchived = !empty($sym['is_archived']);
                            $isEditing = $editingSymbolId === $sym['id'];
                        @endphp
                        <div wire:key="sym-{{ $currentSymbolLayout }}-{{ $sym['id'] }}"
                             class="group border flex flex-col justify-between transition-all relative {{ $currentSymbolLayout === 'small' ? 'rounded-xl p-2.5' : ($currentSymbolLayout === 'large' ? 'rounded-3xl p-5' : 'rounded-2xl p-3.5') }} {{ $isArchived ? 'bg-gray-50/60 dark:bg-gray-950/40 border-gray-200/70 dark:border-gray-800/60 opacity-70 hover:opacity-100' : 'bg-white dark:bg-gray-900/60 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] hover:shadow-md' }}">

                            {{-- Obere Zeile: Kategorie-Badge & Direkt-Aktionen --}}
                            <div class="flex items-center justify-between gap-1 {{ $currentSymbolLayout === 'small' ? 'mb-1.5' : 'mb-2.5' }}">
                                @if($isArchived)
                                    <span class="px-1.5 py-0.5 rounded-md text-[8px] font-black uppercase tracking-wider bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 truncate">
                                        Archiv
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md {{ $currentSymbolLayout === 'small' ? 'text-[8px] max-w-[65px]' : 'text-[9px] max-w-[110px]' }} font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 truncate" title="{{ $sym['category'] }}">
                                        {{ $sym['category'] }}
                                    </span>
                                @endif

                                <div class="flex items-center gap-0.5">
                                    <button type="button"
                                            wire:click="startEditSymbol('{{ $sym['id'] }}', '{{ addslashes($sym['name']) }}', '{{ addslashes($sym['category']) }}')"
                                            class="p-1 rounded-lg text-gray-400 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                            title="Name & Kategorie bearbeiten">
                                        <x-heroicon-o-pencil class="{{ $currentSymbolLayout === 'small' ? 'w-3 h-3' : 'w-3.5 h-3.5' }}" />
                                    </button>
                                    <button type="button"
                                            wire:click="toggleArchiveSymbol('{{ $sym['id'] }}')"
                                            class="p-1 rounded-lg transition-colors cursor-pointer {{ $isArchived ? 'text-amber-500 hover:text-emerald-500 hover:bg-emerald-500/10' : 'text-gray-400 hover:text-amber-500 hover:bg-amber-500/10' }}"
                                            title="{{ $isArchived ? 'Wieder aktivieren (im Shop einblenden)' : 'Archivieren (im Shop ausblenden)' }}">
                                        @if($isArchived)
                                            <x-heroicon-o-eye class="{{ $currentSymbolLayout === 'small' ? 'w-3 h-3' : 'w-3.5 h-3.5' }}" />
                                        @else
                                            <x-heroicon-o-archive-box class="{{ $currentSymbolLayout === 'small' ? 'w-3 h-3' : 'w-3.5 h-3.5' }}" />
                                        @endif
                                    </button>
                                    <button type="button"
                                            wire:click="deleteSymbol('{{ $sym['id'] }}')"
                                            wire:confirm="Symbol „{{ $sym['name'] }}“ endgültig löschen? (Tipp: Nutze „Archivieren“, falls das Symbol nur im Shop ausgeblendet werden soll.)"
                                            class="p-1 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer"
                                            title="Endgültig löschen">
                                        <x-heroicon-o-trash class="{{ $currentSymbolLayout === 'small' ? 'w-3 h-3' : 'w-3.5 h-3.5' }}" />
                                    </button>
                                </div>
                            </div>

                            {{-- SVG Vorschau-Box (Hell oder Dunkel / Lasergravur-Optik) --}}
                            <div class="aspect-square rounded-xl border flex items-center justify-center {{ $currentSymbolLayout === 'small' ? 'p-2.5 mb-1.5' : ($currentSymbolLayout === 'large' ? 'p-7 mb-3.5 rounded-2xl' : 'p-4 mb-2.5') }} transition-colors {{ $previewSurface === 'dark' ? 'bg-gray-950 border-gray-800 shadow-inner' : 'bg-amber-50/40 dark:bg-gray-800/40 border-amber-200/50 dark:border-gray-700/60' }}">
                                <img src="{{ $sym['url'] }}"
                                     alt="{{ $sym['name'] }}"
                                     loading="lazy"
                                     style="{{ $previewSurface === 'dark' ? 'filter: brightness(0) invert(1) drop-shadow(0px 0px 2px rgba(255,255,255,0.45)); opacity: 0.92;' : '' }}"
                                     class="w-full h-full object-contain transition-transform duration-200 group-hover:scale-105 pointer-events-none" />
                            </div>

                            {{-- Unterer Bereich: Name & Kategorie (oder Inline-Editor) --}}
                            @if($isEditing)
                                <form wire:submit.prevent="saveEditSymbol" class="space-y-2 pt-1 border-t border-gray-100 dark:border-gray-800">
                                    <input type="text"
                                           wire:model="editSymbolName"
                                           placeholder="Anzeigename..."
                                           class="w-full px-2.5 py-1 rounded-lg border border-[var(--theme-color)] bg-white dark:bg-gray-950 text-xs font-bold text-gray-900 dark:text-white focus:outline-none" />
                                    <select wire:model="editSymbolCategory"
                                            class="w-full px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-950 text-[11px] text-gray-800 dark:text-gray-200 focus:outline-none">
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex items-center gap-1.5 pt-0.5">
                                        <button type="submit" class="flex-1 py-1 rounded-lg bg-[var(--theme-color)] text-gray-900 font-black text-[10px] uppercase tracking-wider cursor-pointer">
                                            Speichern
                                        </button>
                                        <button type="button" wire:click="cancelEditSymbol" class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 text-[10px] font-bold cursor-pointer">
                                            ✕
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div>
                                    <div class="{{ $currentSymbolLayout === 'small' ? 'text-[11px]' : ($currentSymbolLayout === 'large' ? 'text-sm' : 'text-xs') }} font-bold text-gray-900 dark:text-white truncate" title="{{ $sym['name'] }}">
                                        {{ $sym['name'] }}
                                    </div>
                                    @if($currentSymbolLayout !== 'small')
                                        <div class="flex items-center justify-between text-[10px] text-gray-400 font-mono mt-0.5">
                                            <span class="truncate max-w-[130px]" title="{{ $sym['file'] }}">{{ basename($sym['file']) }}</span>
                                            <span>{{ $sym['size_kb'] }} KB</span>
                                        </div>
                                    @endif
                                    @if($currentSymbolLayout === 'large')
                                        <div class="mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                                            <select wire:change="quickChangeCategory('{{ $sym['id'] }}', '{{ addslashes($sym['name']) }}', $event.target.value)"
                                                    class="flex-1 px-2.5 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-950 text-[11px] font-semibold text-gray-700 dark:text-gray-300 focus:outline-none focus:border-[var(--theme-color)] cursor-pointer">
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat }}" @selected($sym['category'] === $cat)>{{ $cat }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button"
                                                    wire:click="toggleArchiveSymbol('{{ $sym['id'] }}')"
                                                    class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer {{ $isArchived ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-amber-500/40 hover:text-amber-600' }}">
                                                {{ $isArchived ? 'Aktivieren' : 'Archivieren' }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="text-center py-14 rounded-3xl border border-dashed border-gray-200 dark:border-gray-800">
                <x-heroicon-o-sparkles class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-2" />
                <p class="text-sm font-bold text-gray-600 dark:text-gray-400">Keine Symbole für diesen Filter gefunden.</p>
                <p class="text-xs text-gray-400 mt-1">Wähle eine andere Kategorie oder lade oben neue SVG-Dateien per Drag & Drop hoch.</p>
            </div>
        @endif
    </div>
</div>
