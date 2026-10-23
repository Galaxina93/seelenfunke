<div x-data="{ expanded: false }" class="bg-white/90 dark:bg-black/40 border border-gray-200 dark:border-gray-800/60 rounded-3xl p-6 sm:p-8 backdrop-blur-md relative dark:shadow-[0_0_20px_rgba(0,0,0,0.3)] mb-8">

    <div wire:init="loadStorageDetails" class="relative z-10 flex flex-col gap-6">

        <!-- COMPACT HEADER VIEW -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">

            <!-- Left: Headline & Tooltip -->
            <div class="flex items-center gap-3">
                <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white uppercase tracking-wider font-mono flex items-center gap-3">
                    <i class="bi bi-hdd-network text-gray-500 dark:text-gray-400"></i>
                    Speicher-Last
                </h2>
                <!-- Tooltip replacing subtext -->
                <div class="group relative flex items-center cursor-help">
                    <i class="bi bi-info-circle text-gray-400 dark:text-gray-500 hover:text-cyan-500 dark:hover:text-cyan-400 transition-colors text-sm sm:text-lg hidden md:block"></i>
                    <div class="absolute left-full ml-3 top-1/2 -translate-y-1/2 z-50 w-64 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-gray-700 dark:text-gray-300 font-mono text-[10px] sm:text-xs">
                        Überwacht die Auslastung der physischen Server-Festplatte. Bei 100% drohen fatale Systemausfälle. Schwellenwerte dienen als Warnsystem.
                    </div>
                </div>
                
                <!-- Display overall Space in Title -->
                <div class="hidden sm:flex ml-4 rounded-full border border-gray-200 dark:border-gray-800 bg-gray-100 dark:bg-gray-900/50 px-3 py-1 font-mono text-[10px] font-bold tracking-widest text-gray-600 dark:text-gray-400">
                    {{ $usedSpaceGb }} GB / {{ $totalSpaceGb }} GB belegt
                </div>
            </div>

            <!-- Middle/Right: Expander -->
            <div class="flex w-full md:w-auto items-center justify-between md:justify-end gap-3 sm:gap-4">
                <button @click="expanded = !expanded" class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-900/80 dark:hover:bg-gray-800 border border-gray-300 dark:border-gray-700 focus:outline-none focus:ring opacity-90 hover:opacity-100 transition duration-300 rounded-xl px-3 sm:px-4 py-2 shadow-sm dark:shadow-inner text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white group">
                    <span class="text-[10px] sm:text-xs font-mono font-bold uppercase tracking-wider" x-text="expanded ? 'Schließen' : 'Details'"></span>
                    <i class="bi transform transition-transform duration-300 group-hover:scale-110" :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
            </div>
        </div>

        <!-- DER ULTIMATIVE BALKEN (IMMER SICHTBAR) -->
        <div class="relative w-full mt-6 sm:mt-8 mb-4 sm:mb-6 px-1 lg:px-6" x-data="{
                    t1: @entangle('threshold1'),
                    t2: @entangle('threshold2'),
                    t3: @entangle('threshold3'),
                    activeThumb: null,
                    trackWidth: 0,
                    trackLeft: 0,
                    _dragHandler: null,
                    _stopDragHandler: null,

                    startDrag(e, thumb) {
                        this.activeThumb = thumb;
                        const rect = this.$refs.track.getBoundingClientRect();
                        this.trackWidth = rect.width;
                        this.trackLeft = rect.left;
                        document.body.style.userSelect = 'none';
                        if(e.stopPropagation) e.stopPropagation();

                        this._dragHandler = this.drag.bind(this);
                        this._stopDragHandler = this.stopDrag.bind(this);
                        window.addEventListener('mousemove', this._dragHandler);
                        window.addEventListener('mouseup', this._stopDragHandler);
                        window.addEventListener('touchmove', this._dragHandler, { passive: true });
                        window.addEventListener('touchend', this._stopDragHandler, { passive: true });
                    },

                    drag(e) {
                        if (!this.activeThumb) return;
                        let clientX = e.clientX || (e.touches && e.touches[0].clientX);
                        if (!clientX) return;

                        let percent = ((clientX - this.trackLeft) / this.trackWidth) * 100;
                        percent = Math.max(0, Math.min(100, Math.round(percent)));

                        if (this.activeThumb === 't1') {
                            this.t1 = Math.min(percent, this.t2 - 2); 
                        } else if (this.activeThumb === 't2') {
                            this.t2 = Math.max(this.t1 + 2, Math.min(percent, this.t3 - 2));
                        } else if (this.activeThumb === 't3') {
                            this.t3 = Math.max(this.t2 + 2, Math.min(percent, 99)); 
                        }
                    },

                    stopDrag() {
                        if (this.activeThumb) {
                            this.$wire.updateThresholds(this.t1, this.t2, this.t3);
                            this.activeThumb = null;
                            document.body.style.userSelect = '';

                            window.removeEventListener('mousemove', this._dragHandler);
                            window.removeEventListener('mouseup', this._stopDragHandler);
                            window.removeEventListener('touchmove', this._dragHandler);
                            window.removeEventListener('touchend', this._stopDragHandler);
                        }
                    },

                    destroy() {
                        if (this._dragHandler) {
                            window.removeEventListener('mousemove', this._dragHandler);
                            window.removeEventListener('touchmove', this._dragHandler);
                        }
                        if (this._stopDragHandler) {
                            window.removeEventListener('mouseup', this._stopDragHandler);
                            window.removeEventListener('touchend', this._stopDragHandler);
                        }
                    }
                }">

            <!-- Balken-Container -->
            <div x-ref="track" class="w-full h-8 bg-gray-200 dark:bg-black/60 rounded-full border border-gray-300 dark:border-gray-800 dark:shadow-inner dark:shadow-[inset_0_2px_15px_rgba(0,0,0,0.8)] relative overflow-visible select-none">

                <!-- Der Füllbalken -->
                <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-1000 ease-out flex items-center justify-end pr-2 overflow-hidden
                    {{ $level === 0 ? 'bg-gradient-to-r from-emerald-600 to-emerald-400 dark:shadow-[0_0_20px_rgba(16,185,129,0.6)]' : '' }}
                    {{ $level === 1 ? 'bg-gradient-to-r from-emerald-500 via-amber-400 to-amber-500 dark:shadow-[0_0_20px_rgba(245,158,11,0.6)]' : '' }}
                    {{ $level === 2 ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-orange-600 dark:shadow-[0_0_20px_rgba(249,115,22,0.6)] animate-pulse' : '' }}
                    {{ $level === 3 ? 'bg-gradient-to-r from-orange-600 via-red-600 to-red-800 dark:shadow-[0_0_30px_rgba(220,38,38,0.8)] animate-pulse' : '' }}
                    {{ $level === 4 ? 'bg-gradient-to-r from-red-600 via-red-800 to-red-950 dark:shadow-[0_0_40px_rgba(220,38,38,0.8)] animate-pulse' : '' }}"
                    style="width: {{ min($percentage, 100) }}%;">

                    <!-- Glanz-Effekt im Balken -->
                    <div class="absolute inset-0 bg-gradient-to-b from-white/20 to-transparent pointer-events-none"></div>

                    <!-- Animierter Streifen (Pulsieren/Bewegen) für High-Load -->
                    @if($level >= 2)
                    <div class="absolute inset-0 opacity-30" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,0.3) 10px, rgba(0,0,0,0.3) 20px);"></div>
                    @endif
                </div>

                <!-- T1: Warnung 1 -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t1}%;`" @mousedown="startDrag($event, 't1')" @touchstart.passive="startDrag($event, 't1')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-amber-500/80 rounded dark:shadow-[0_0_10px_rgba(245,158,11,0.5)] group-hover:bg-amber-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-amber-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t1"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-amber-600/70">WARNUNG 1</span>
                    </div>
                </div>

                <!-- T2: Warnung 2 -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t2}%;`" @mousedown="startDrag($event, 't2')" @touchstart.passive="startDrag($event, 't2')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-orange-500/90 rounded dark:shadow-[0_0_10px_rgba(249,115,22,0.8)] group-hover:bg-orange-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-orange-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t2"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-orange-600/70">WARNUNG 2</span>
                    </div>
                </div>

                <!-- T3: Kritisch -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t3}%;`" @mousedown="startDrag($event, 't3')" @touchstart.passive="startDrag($event, 't3')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-rose-500/90 rounded dark:shadow-[0_0_10px_rgba(225,29,72,0.8)] group-hover:bg-rose-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-rose-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t3"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-rose-600/70">KRITISCH</span>
                    </div>
                </div>

                <!-- 100% Festplatte Voll (Fix) -->
                <div class="absolute w-5 h-20 sm:h-18 -top-6 sm:-top-4 right-0 flex justify-end z-20 group">
                    <div class="w-1.5 h-full bg-red-600 rounded pointer-events-none dark:shadow-[0_0_15px_rgba(220,38,38,1)]"></div>
                    <div class="absolute -top-6 sm:-top-10 right-0 text-[10px] sm:text-[12px] font-black text-red-500 font-mono text-right whitespace-nowrap dark:drop-shadow-lg pr-2 pointer-events-none">
                        100%<br><span class="text-[7px] sm:text-[9px] uppercase tracking-wider text-red-400/90">DISK FULL</span>
                    </div>
                </div>

            </div>

            <!-- Float-Indikator des aktuellen State über dem Balken -->
            <div class="absolute z-30 transition-all duration-1000 ease-out pointer-events-none" style="left: calc({{ min($percentage, 100) }}% - 15px); top: -24px;">
                <div class="relative bg-gray-900 dark:bg-white text-white dark:text-black font-black font-mono text-xs sm:text-sm px-1.5 sm:px-2 py-0.5 sm:py-1 rounded shadow-sm dark:shadow-lg border-2 border-gray-900 dark:border-white {{ $level >= 2 ? 'animate-bounce' : '' }}">
                    {{ $percentage }}%
                    <div class="absolute w-1.5 sm:w-2 h-1.5 sm:h-2 bg-gray-900 dark:bg-white rotate-45 -bottom-1 left-1/2 -translate-x-1/2 rounded-[1px]"></div>
                </div>
            </div>
        </div>

        <!-- MINI LOG (IMMER SICHTBAR, UNTER DEM BALKEN) -->
        <div x-show="!expanded" class="px-1 md:px-6 transition-opacity duration-300">
            @if(count($actionLog) > 0)
                <div class="text-[9px] sm:text-[10px] font-mono text-gray-500 dark:text-gray-400 flex gap-2 sm:gap-3 items-center truncate">
                    <span class="text-[8px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700 uppercase shrink-0">Letztes Event</span>
                    <span class="text-gray-700 dark:text-gray-400 truncate">
                        >_ {{ $actionLog[count($actionLog)-1]['msg'] }}
                    </span>
                </div>
            @endif
        </div>

        <!-- EXPANDED VIEW -->
        <div x-show="expanded" x-collapse.duration.400ms class="pt-6 sm:pt-8 border-t border-gray-200 dark:border-gray-800/60 mt-1 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- One-Click-Cleaner Panel -->
                <div class="col-span-1 flex flex-col gap-5">
                    <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 shadow-sm dark:shadow-inner h-full flex flex-col">
                        <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-black tracking-widest mb-4 border-b border-gray-200 dark:border-gray-800/80 pb-3 flex items-center justify-between">
                            <span>System-Reinigung</span>
                            <button wire:click="refreshAnalysis" wire:loading.attr="disabled" title="Speicher-Analyse jetzt live neu scannen" class="text-gray-400 hover:text-emerald-500 dark:hover:text-emerald-400 transition-colors flex items-center gap-1 text-[9px] font-mono">
                                <i class="bi bi-arrow-clockwise" wire:loading.class="animate-spin" wire:target="refreshAnalysis"></i>
                                <span>Scan</span>
                            </button>
                        </div>
                        <div class="flex-1 flex flex-col gap-2.5 justify-center">
                            
                            <button wire:click="clearLaravelLogs" wire:loading.attr="disabled" title="Pfad: storage/logs/ - Leert sämtliche laravel.log Dateien, in denen sich Fehler oder Zugriffe stauen." class="w-full flex items-center justify-between bg-white dark:bg-black border border-gray-200 dark:border-gray-800 hover:border-emerald-500/50 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors p-2.5 px-3 rounded-xl group relative overflow-hidden shadow-sm">
                                <div class="absolute inset-0 bg-emerald-500/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                <div class="flex items-center gap-3 relative z-10">
                                    <i class="bi bi-journal-x text-emerald-500 text-base"></i>
                                    <span class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Laravel Logs leeren</span>
                                </div>
                                <div class="flex items-center gap-2 relative z-10">
                                    <i class="bi bi-info-circle text-gray-400 dark:text-gray-500 hover:text-emerald-500 dark:hover:text-emerald-400 cursor-help transition-colors"></i>
                                    <i class="bi bi-chevron-right text-gray-400 dark:text-gray-600 transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </button>

                            <button wire:click="clearFrameworkCache" wire:loading.attr="disabled" title="Pfad: storage/framework/views - Erzwingt das Neuerstellen der kompilierten Blade-Sichten ohne aktive User-Sessions zu trennen." class="w-full flex items-center justify-between bg-white dark:bg-black border border-gray-200 dark:border-gray-800 hover:border-cyan-500/50 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors p-2.5 px-3 rounded-xl group relative overflow-hidden shadow-sm">
                                <div class="absolute inset-0 bg-cyan-500/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                <div class="flex items-center gap-3 relative z-10">
                                    <i class="bi bi-cpu text-cyan-500 text-base"></i>
                                    <span class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Views/Cache purgen</span>
                                </div>
                                <div class="flex items-center gap-2 relative z-10">
                                    <i class="bi bi-info-circle text-gray-400 dark:text-gray-500 hover:text-cyan-500 dark:hover:text-cyan-400 cursor-help transition-colors"></i>
                                    <i class="bi bi-chevron-right text-gray-400 dark:text-gray-600 transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </button>

                            <button wire:click="clearTempDirectory" wire:loading.attr="disabled" title="Pfad: storage/app/temp/* (livewire-tmp, mail-attachments, backup-temp) - Löscht sämtliche temporären Upload- und Zwischen-Dateien." class="w-full flex items-center justify-between bg-white dark:bg-black border border-gray-200 dark:border-gray-800 hover:border-amber-500/50 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors p-2.5 px-3 rounded-xl group relative overflow-hidden shadow-sm">
                                <div class="absolute inset-0 bg-amber-500/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                <div class="flex items-center gap-3 relative z-10">
                                    <i class="bi bi-trash2 text-amber-500 text-base"></i>
                                    <span class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Temp-Zone leeren</span>
                                </div>
                                <div class="flex items-center gap-2 relative z-10">
                                    <i class="bi bi-info-circle text-gray-400 dark:text-gray-500 hover:text-amber-500 dark:hover:text-amber-400 cursor-help transition-colors"></i>
                                    <i class="bi bi-chevron-right text-gray-400 dark:text-gray-600 transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </button>

                            <button wire:click="runOrphanedCleanup" wire:loading.attr="disabled" title="Führt shop:cleanup-orphaned-uploads aus: Bereinigt verwaiste Warenkorb-Uploads (>48h), Temp-Dateien (>24h), KI-Exporte (>7d) & abgelaufene Warenkörbe (>14d)." class="w-full flex items-center justify-between bg-white dark:bg-black border border-gray-200 dark:border-gray-800 hover:border-purple-500/50 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors p-2.5 px-3 rounded-xl group relative overflow-hidden shadow-sm">
                                <div class="absolute inset-0 bg-purple-500/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                <div class="flex items-center gap-3 relative z-10">
                                    <i class="bi bi-recycle text-purple-500 text-base" wire:loading.class="animate-spin" wire:target="runOrphanedCleanup"></i>
                                    <span class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Müllabfuhr starten</span>
                                </div>
                                <div class="flex items-center gap-2 relative z-10">
                                    <i class="bi bi-info-circle text-gray-400 dark:text-gray-500 hover:text-purple-500 dark:hover:text-purple-400 cursor-help transition-colors"></i>
                                    <i class="bi bi-chevron-right text-gray-400 dark:text-gray-600 transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </button>

                        </div>
                    </div>
                </div>

                <!-- Ordnergrößen & Action Log -->
                <div class="col-span-1 lg:col-span-2 flex flex-col gap-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 h-full">
                        
                        <!-- Ordnerstruktur Tabelle -->
                        <div class="bg-gray-50 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 flex-1 shadow-sm dark:shadow-inner relative flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-center mb-3 border-b border-gray-200 dark:border-gray-800/80 pb-2">
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-black tracking-widest flex items-center gap-2">
                                        <i class="bi bi-folder2-open text-gray-400 dark:text-gray-500"></i> storage/ Hauptordner
                                    </div>
                                    <div class="text-[10px] font-mono text-gray-500">
                                        Gesamt: <span class="text-gray-900 dark:text-white font-bold">{{ $totalStorageAppGb }} GB</span>
                                    </div>
                                </div>
                                
                                <div wire:loading wire:target="loadStorageDetails,refreshAnalysis" class="absolute inset-0 z-20 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
                                </div>

                                <div class="space-y-1.5 mt-3">
                                    @forelse($folderSizes as $folder)
                                        <div class="flex justify-between items-center bg-white dark:bg-black/40 rounded-lg p-2 px-3 border border-gray-200 dark:border-gray-800/50">
                                            <div class="text-xs font-mono text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                                <i class="bi bi-folder-fill text-emerald-600/80 dark:text-emerald-500/80"></i>
                                                <span>storage/{{ $folder['name'] }}</span>
                                            </div>
                                            <div class="text-[10px] font-mono font-bold {{ $folder['size_mb'] > 1000 ? 'text-red-500 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">{{ $folder['size_mb'] }} MB</div>
                                        </div>
                                    @empty
                                        <div class="text-[10px] text-gray-400 dark:text-gray-500 font-mono text-center py-4">Lade Ordner...</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-200/70 dark:border-gray-800/60 flex items-center justify-between text-[10px] font-mono text-gray-500 dark:text-gray-400">
                                <span>Erfasste Dateien im Storage:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ number_format($totalFilesCount, 0, ',', '.') }} Dateien</span>
                            </div>
                        </div>

                        <!-- Action Log Terminal (Light & Dark Mode fähig) -->
                        <div class="flex-1 bg-gray-100/90 dark:bg-[#0a0a0c] rounded-2xl border border-gray-200 dark:border-gray-800/80 relative overflow-hidden flex flex-col shadow-sm dark:shadow-inner">
                            <div class="absolute top-0 inset-x-0 h-8 bg-gray-200/80 dark:bg-gray-900 border-b border-gray-300/80 dark:border-gray-800/80 flex items-center px-3 gap-2">
                                <div class="w-2.5 h-2.5 rounded-full bg-red-500/70 dark:bg-red-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-amber-500/70 dark:bg-yellow-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500/70 dark:bg-green-500/50"></div>
                                <span class="ml-2 text-[9px] uppercase tracking-widest text-gray-600 dark:text-gray-400 font-mono font-bold">Speicher-Log</span>
                            </div>
                            <div class="p-4 pt-11 pb-4 space-y-2.5 h-full overflow-y-auto custom-scrollbar min-h-[170px]">
                                @forelse($actionLog as $log)
                                    <div class="flex items-start gap-2.5 font-mono text-[10px] sm:text-xs">
                                        <div class="mt-0.5 shrink-0">
                                             @if($log['type'] === 'success') <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-500"></i> @endif
                                             @if($log['type'] === 'warning') <i class="bi bi-exclamation-triangle-fill text-amber-600 dark:text-amber-500"></i> @endif
                                             @if($log['type'] === 'danger') <i class="bi bi-exclamation-octagon-fill text-orange-600 dark:text-orange-500 pulse"></i> @endif
                                             @if($log['type'] === 'critical') <i class="bi bi-shield-fill-x text-red-600 dark:text-red-500 pulse"></i> @endif
                                        </div>
                                        <span class="{{ $log['type'] === 'success' ? 'text-emerald-700 dark:text-emerald-400' : '' }} {{ $log['type'] === 'warning' ? 'text-amber-700 dark:text-amber-400' : '' }} {{ $log['type'] === 'danger' ? 'text-orange-700 dark:text-orange-400 font-bold' : '' }} {{ $log['type'] === 'critical' ? 'text-red-700 dark:text-red-400 font-black tracking-wide' : '' }}">
                                            >_ {{ $log['msg'] }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="text-gray-500 dark:text-gray-500 text-[10px] font-mono">>_ Keine Aufzeichnungen ...</div>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- 3-ZONEN SPEICHER-ARCHITEKTUR AUFSCHLÜSSELUNG -->
            <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-6 shadow-sm dark:shadow-inner relative">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4 border-b border-gray-200 dark:border-gray-800/80 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-diagram-3-fill text-emerald-500 text-sm"></i>
                        <span class="text-[10px] sm:text-xs text-gray-700 dark:text-gray-300 uppercase font-black tracking-widest font-mono">
                            3-Zonen Speicher-Architektur (Abteilungs-Aufschlüsselung)
                        </span>
                        <div class="group relative flex items-center cursor-help">
                            <i class="bi bi-exclamation-circle text-gray-400 hover:text-emerald-500 dark:hover:text-emerald-400 transition-colors text-xs"></i>
                            <div class="absolute left-0 bottom-full mb-2 z-50 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-gray-700 dark:text-gray-300 font-mono text-[10px] leading-relaxed pointer-events-none">
                                Strikte Trennung zwischen geschütztem <strong>Privat-Tresor</strong> (Buchhaltung, ERiC, DHL, KI-Workspace), <strong>Öffentlichem Shop-Speicher</strong> (Produkte, 3D-Modelle, Marketing) und <strong>Temp/System-Cache</strong>.
                            </div>
                        </div>
                    </div>
                    <span class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                        Konsolidierte Struktur (DE-Abteilungen)
                    </span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-5">
                    @foreach($storageZones as $zone)
                        @php
                            $borderColor = match($zone['color'] ?? 'emerald') {
                                'emerald' => 'border-emerald-500/30 dark:border-emerald-500/20',
                                'cyan' => 'border-cyan-500/30 dark:border-cyan-500/20',
                                'amber' => 'border-amber-500/30 dark:border-amber-500/20',
                                default => 'border-gray-200 dark:border-gray-800',
                            };
                            $badgeClass = match($zone['color'] ?? 'emerald') {
                                'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                'cyan' => 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border-cyan-500/20',
                                'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                default => 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/20',
                            };
                            $barClass = match($zone['color'] ?? 'emerald') {
                                'emerald' => 'bg-emerald-500',
                                'cyan' => 'bg-cyan-500',
                                'amber' => 'bg-amber-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <div class="bg-white dark:bg-black/40 border {{ $borderColor }} rounded-xl p-4 flex flex-col justify-between shadow-sm">
                            <div>
                                <!-- Zone Header -->
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded-lg border {{ $badgeClass }} flex items-center justify-center shrink-0">
                                            <i class="bi {{ $zone['icon'] }} text-xs"></i>
                                        </span>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <h4 class="text-xs font-bold font-mono text-gray-900 dark:text-white">{{ $zone['title'] }}</h4>
                                                <div class="group relative inline-flex items-center cursor-help">
                                                    <i class="bi bi-exclamation-circle text-[10px] text-gray-400 hover:text-emerald-500"></i>
                                                    <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 z-50 w-60 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2.5 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-[10px] font-mono text-gray-600 dark:text-gray-300 pointer-events-none">
                                                        {{ $zone['description'] }}
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-[9px] font-mono text-gray-500 dark:text-gray-400">{{ $zone['subtitle'] }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs font-mono font-black text-gray-900 dark:text-white">{{ $zone['size_mb'] }} MB</div>
                                        <div class="text-[9px] font-mono text-gray-400">{{ number_format($zone['files_count'], 0, ',', '.') }} Dateien</div>
                                    </div>
                                </div>

                                <!-- Share Bar -->
                                <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden my-2.5">
                                    <div class="h-full {{ $barClass }} rounded-full transition-all duration-500" style="width: {{ min(100, max(2, $zone['share_percent'])) }}%"></div>
                                </div>

                                <!-- Department List -->
                                <div class="space-y-1.5 mt-3">
                                    @forelse($zone['departments'] as $dept)
                                        <div class="flex items-center justify-between text-[10px] font-mono bg-gray-50/80 dark:bg-gray-900/60 px-2.5 py-1.5 rounded-lg border border-gray-200/60 dark:border-gray-800/60">
                                            <div class="flex items-center gap-1.5 truncate pr-2">
                                                <i class="bi bi-folder2 text-gray-400 dark:text-gray-500"></i>
                                                <span class="text-gray-700 dark:text-gray-300 font-semibold truncate">{{ $dept['name'] }}</span>
                                                <div class="group relative inline-flex items-center cursor-help shrink-0">
                                                    <i class="bi bi-exclamation-circle text-[9px] text-gray-400 hover:text-cyan-500"></i>
                                                    <div class="absolute left-0 bottom-full mb-1.5 z-50 w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-[9px] font-mono text-gray-600 dark:text-gray-300 pointer-events-none">
                                                        {{ $dept['desc'] }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-[9px] text-gray-400 dark:text-gray-500">{{ $dept['files_count'] }} Dat.</span>
                                                <span class="font-bold text-gray-800 dark:text-gray-200 min-w-[52px] text-right">{{ $dept['size_mb'] }} MB</span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-[10px] font-mono text-gray-400 text-center py-2">Leer (0 MB)</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-800/50 flex justify-between items-center text-[9px] font-mono text-gray-400">
                                <span>Anteil am Storage:</span>
                                <span class="font-bold text-gray-600 dark:text-gray-300">{{ $zone['share_percent'] }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- AUTOMATISCHE SPEICHER-BEREINIGUNG (CRONJOBS KOMPAKT-ANSICHT) -->
            <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-6 shadow-sm dark:shadow-inner relative">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4 border-b border-gray-200 dark:border-gray-800/80 pb-3">
                    <div class="flex items-center gap-2.5">
                        <i class="bi bi-clock-history text-cyan-500 text-sm"></i>
                        <span class="text-[10px] sm:text-xs text-gray-700 dark:text-gray-300 uppercase font-black tracking-widest font-mono">
                            Automatische Speicher-Bereinigung & Backup-Cronjobs
                        </span>
                        <div class="group relative flex items-center cursor-help">
                            <i class="bi bi-exclamation-circle text-gray-400 hover:text-cyan-500 dark:hover:text-cyan-400 transition-colors text-xs"></i>
                            <div class="absolute left-0 bottom-full mb-2 z-50 w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-gray-700 dark:text-gray-300 font-mono text-[10px] leading-relaxed pointer-events-none">
                                Zeigt alle hinterlegten Hintergrund-Cronjobs für die automatische Datenmüll-Bereinigung und Backup-Rotation inklusive Countdown bis zur nächsten Ausführung.
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-[10px] font-mono font-bold flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Auto-Müllabfuhr aktiv
                        </span>
                    </div>
                </div>

                <!-- Aufbewahrungs-Richtlinien (Retention Policy Pills) -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-4">
                    <div class="bg-white dark:bg-black/40 border border-gray-200/80 dark:border-gray-800 rounded-lg px-2.5 py-1.5 flex items-center justify-between text-[10px] font-mono">
                        <span class="text-gray-500 dark:text-gray-400">Temp-Zone:</span>
                        <span class="font-bold text-amber-600 dark:text-amber-400">&gt; 24 Std.</span>
                    </div>
                    <div class="bg-white dark:bg-black/40 border border-gray-200/80 dark:border-gray-800 rounded-lg px-2.5 py-1.5 flex items-center justify-between text-[10px] font-mono">
                        <span class="text-gray-500 dark:text-gray-400">Warenkorb-Uploads:</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">&gt; 48 Std.</span>
                    </div>
                    <div class="bg-white dark:bg-black/40 border border-gray-200/80 dark:border-gray-800 rounded-lg px-2.5 py-1.5 flex items-center justify-between text-[10px] font-mono">
                        <span class="text-gray-500 dark:text-gray-400">KI-Exporte:</span>
                        <span class="font-bold text-cyan-600 dark:text-cyan-400">&gt; 7 Tage</span>
                    </div>
                    <div class="bg-white dark:bg-black/40 border border-gray-200/80 dark:border-gray-800 rounded-lg px-2.5 py-1.5 flex items-center justify-between text-[10px] font-mono">
                        <span class="text-gray-500 dark:text-gray-400">Offene Warenkörbe:</span>
                        <span class="font-bold text-purple-600 dark:text-purple-400">&gt; 14 Tage</span>
                    </div>
                    <div class="bg-white dark:bg-black/40 border border-gray-200/80 dark:border-gray-800 rounded-lg px-2.5 py-1.5 flex items-center justify-between text-[10px] font-mono col-span-2 sm:col-span-1">
                        <span class="text-gray-500 dark:text-gray-400">DB-Backups:</span>
                        <span class="font-bold text-rose-600 dark:text-rose-400">&gt; 15 Tage</span>
                    </div>
                </div>

                <!-- Compact Cronjob Cards -->
                <div class="space-y-2.5">
                    @forelse($this->storageCronjobs as $job)
                        @php
                            $nextRun = $job->next_run_at;
                            $lastRun = $job->last_run_at ?? $job->previous_run_at;
                            $humanSchedule = match($job->schedule) {
                                '0 4 * * *' => 'Täglich um 04:00 Uhr',
                                '30 3 * * *' => 'Täglich um 03:30 Uhr',
                                '0 3 * * *' => 'Täglich um 03:00 Uhr',
                                'daily', '0 0 * * *' => 'Täglich um 00:00 Uhr',
                                default => $job->schedule,
                            };
                        @endphp
                        <div class="relative bg-white dark:bg-black/40 rounded-xl border border-gray-200 dark:border-gray-800 hover:border-cyan-500/40 transition-all duration-200 overflow-hidden shadow-sm">
                            <!-- Left Status Indicator Bar -->
                            <div class="absolute top-0 left-0 w-1 h-full {{ !$job->is_active ? 'bg-gray-400 dark:bg-gray-600' : ($job->status === 'error' ? 'bg-red-500' : 'bg-emerald-500') }}"></div>

                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 p-3 pl-4 items-center">
                                <!-- Job Name & Description -->
                                <div class="lg:col-span-4">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold font-mono text-gray-900 dark:text-white">{{ $job->name }}</span>
                                        <div class="group relative inline-flex items-center cursor-help">
                                            <i class="bi bi-exclamation-circle text-[10px] text-gray-400 hover:text-cyan-500"></i>
                                            <div class="absolute left-0 bottom-full mb-1.5 z-50 w-64 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2.5 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-[10px] font-mono text-gray-600 dark:text-gray-300 pointer-events-none">
                                                {{ $job->description }}
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ $job->description }}</p>
                                </div>

                                <!-- Command & Schedule Time -->
                                <div class="lg:col-span-3 flex flex-wrap items-center gap-1.5">
                                    <code class="text-[10px] font-mono px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-cyan-400 border border-gray-200 dark:border-gray-700">
                                        {{ $job->command }}
                                    </code>
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20 font-semibold">
                                        <i class="bi bi-alarm me-1"></i>{{ $humanSchedule }}
                                    </span>
                                </div>

                                <!-- Timing: Last Run & Next Run Countdown -->
                                <div class="lg:col-span-3 flex flex-col sm:flex-row lg:flex-col gap-1 sm:gap-4 lg:gap-0.5 text-[10px] font-mono">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-gray-400 dark:text-gray-500">Zuletzt:</span>
                                        <span class="font-bold {{ $job->status === 'error' ? 'text-red-500' : 'text-gray-700 dark:text-gray-300' }}">
                                            {{ $job->last_run_at ? $job->last_run_at->diffForHumans() : 'Geplant (' . $humanSchedule . ')' }}
                                        </span>
                                    </div>
                                    @if($job->is_active && $nextRun)
                                        <div class="flex items-center gap-1.5" x-data="{
                                            nextRunTs: {{ $nextRun->timestamp * 1000 }},
                                            countdown: '',
                                            init() { this.update(); setInterval(() => this.update(), 1000); },
                                            update() {
                                                let diff = Math.max(0, this.nextRunTs - Date.now());
                                                if (diff === 0) { this.countdown = 'Jetzt'; return; }
                                                let h = Math.floor(diff / 3600000);
                                                let m = Math.floor((diff % 3600000) / 60000);
                                                let s = Math.floor((diff % 60000) / 1000);
                                                let parts = [];
                                                if (h > 0) parts.push(h + 'h');
                                                if (m > 0 || h > 0) parts.push(m + 'm');
                                                parts.push(s + 's');
                                                this.countdown = parts.join(' ');
                                            }
                                        }">
                                            <span class="text-gray-400 dark:text-gray-500">Nächster Lauf:</span>
                                            <span class="text-cyan-600 dark:text-cyan-400 font-bold" x-text="countdown"></span>
                                            <span class="text-gray-400 text-[9px]">({{ $nextRun->format('d.m. H:i') }})</span>
                                        </div>
                                    @else
                                        <div class="text-gray-400 italic">Pausiert</div>
                                    @endif
                                </div>

                                <!-- Actions: Run Now & Toggle -->
                                <div class="lg:col-span-2 flex items-center justify-end gap-2.5">
                                    <button wire:click="runStorageCronjob('{{ $job->id }}')"
                                            wire:loading.attr="disabled"
                                            {{ !$job->is_active ? 'disabled' : '' }}
                                            class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold uppercase tracking-wider transition-all flex items-center gap-1.5 border
                                            {{ $job->is_active ? 'bg-gray-100 hover:bg-emerald-500/10 dark:bg-gray-800 dark:hover:bg-emerald-500/20 text-gray-700 hover:text-emerald-700 dark:text-gray-300 dark:hover:text-emerald-300 border-gray-200 dark:border-gray-700 hover:border-emerald-500/40' : 'bg-gray-100 dark:bg-gray-900 text-gray-400 cursor-not-allowed border-gray-200 dark:border-gray-800' }}">
                                        <i class="bi bi-play-fill text-xs" wire:loading.remove wire:target="runStorageCronjob('{{ $job->id }}')"></i>
                                        <i class="bi bi-arrow-repeat animate-spin text-xs text-emerald-500" wire:loading wire:target="runStorageCronjob('{{ $job->id }}')"></i>
                                        <span>Starten</span>
                                    </button>

                                    <button wire:click="toggleStorageCronjob('{{ $job->id }}')"
                                            title="{{ $job->is_active ? 'Cronjob pausieren' : 'Cronjob aktivieren' }}"
                                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $job->is_active ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-700' }}">
                                        <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $job->is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-xs font-mono text-gray-400 py-3">Keine Speicher-Cronjobs konfiguriert.</div>
                    @endforelse
                </div>
            </div>

            <!-- Größte Dateien Details (mit Info-Hover ! und Copy-Icon) -->
            <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 shadow-sm dark:shadow-inner relative">
                <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-black tracking-widest flex items-center justify-between mb-3 border-b border-gray-200 dark:border-gray-800/80 pb-2">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-file-earmark-bar-graph text-gray-400 dark:text-gray-500"></i> Die 10 größten Dateien im gesamten Storage
                    </span>
                    <span class="text-[9px] font-mono text-gray-400 lowercase">hover auf (!) für Details & Kopieren</span>
                </div>
                
                <div wire:loading wire:target="loadStorageDetails,refreshAnalysis" class="absolute inset-0 z-20 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[9px] uppercase tracking-widest text-gray-500 border-b border-gray-200 dark:border-gray-800/80">
                                <th class="pb-2 font-medium">Dateipfad & Info</th>
                                <th class="pb-2 font-medium text-right">Größe (MB)</th>
                                <th class="pb-2 font-medium text-right hidden sm:table-cell">Letzte Änd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($largestFiles as $f)
                                <tr class="border-b border-gray-200/60 dark:border-gray-800/40 last:border-0 hover:bg-gray-100/50 dark:hover:bg-black/20 transition-colors">
                                    <td class="py-2 text-[10px] sm:text-xs font-mono text-gray-800 dark:text-gray-300">
                                        <div class="flex items-center gap-2">
                                            <!-- Info Hover (!) + Copy Button -->
                                            <div class="relative inline-flex items-center gap-1 shrink-0" x-data="{ copied: false, infoText: @js('Datei: ' . ($f['name'] ?? basename($f['path'])) . ' | Pfad: storage/' . $f['path'] . ' | Größe: ' . $f['size_mb'] . ' MB (' . number_format($f['size'] ?? 0, 0, ',', '.') . ' Bytes) | Geändert: ' . $f['last_modified']) }">
                                                <div class="group/finfo relative inline-flex items-center">
                                                    <span class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-cyan-500/20 hover:text-cyan-600 dark:hover:text-cyan-400 flex items-center justify-center text-[9px] font-bold cursor-help transition-colors">
                                                        !
                                                    </span>
                                                    <div class="absolute left-0 bottom-full mb-1.5 z-50 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2.5 rounded-xl shadow-xl opacity-0 invisible group-hover/finfo:opacity-100 group-hover/finfo:visible transition-all text-[10px] font-mono text-gray-700 dark:text-gray-300 pointer-events-none space-y-1">
                                                        <div class="font-bold text-gray-900 dark:text-white break-all">{{ $f['name'] ?? basename($f['path']) }}</div>
                                                        <div class="text-gray-500 dark:text-gray-400 break-all">Pfad: storage/{{ $f['path'] }}</div>
                                                        <div class="flex justify-between pt-1 border-t border-gray-100 dark:border-gray-800">
                                                            <span>Typ: .{{ $f['ext'] ?? pathinfo($f['path'], PATHINFO_EXTENSION) }}</span>
                                                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $f['size_mb'] }} MB</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText(infoText); copied = true; setTimeout(() => copied = false, 2000)"
                                                        title="Datei-Infos & Pfad kopieren"
                                                        class="p-0.5 rounded text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center justify-center transition-colors cursor-pointer">
                                                    <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-emerald-500 transition-colors" />
                                                    <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                                                </button>
                                            </div>
                                            <span class="truncate max-w-[160px] sm:max-w-[380px] md:max-w-[520px]" title="storage/{{ $f['path'] }}">
                                                storage/{{ $f['path'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 text-[10px] sm:text-xs font-mono {{ $f['size_mb'] > 50 ? 'text-red-500 dark:text-red-400 font-bold' : 'text-gray-600 dark:text-gray-400' }} text-right whitespace-nowrap">
                                        {{ $f['size_mb'] }} MB
                                    </td>
                                    <td class="py-2 text-[10px] font-mono text-gray-500 text-right hidden sm:table-cell whitespace-nowrap">
                                        {{ $f['last_modified'] }}
                                    </td>
                                </tr>
                            @endforeach
                            @if(count($largestFiles) === 0)
                                <tr><td colspan="3" class="text-center text-[10px] text-gray-400 dark:text-gray-600 py-3 font-mono">Keine Daten geladen...</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- END EXPANDED VIEW -->

    </div>
</div>
