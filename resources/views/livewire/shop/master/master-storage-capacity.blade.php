<div x-data="{ expanded: false }" x-init="$wire.loadStorageDetails()" class="bg-black/40 border border-gray-800/60 rounded-3xl p-6 sm:p-8 backdrop-blur-md relative shadow-[0_0_20px_rgba(0,0,0,0.3)] mb-8">

    <div class="relative z-10 flex flex-col gap-6">

        <!-- COMPACT HEADER VIEW -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">

            <!-- Left: Headline & Tooltip -->
            <div class="flex items-center gap-3">
                <h2 class="text-xl sm:text-2xl font-black text-white uppercase tracking-wider font-mono flex items-center gap-3">
                    <i class="bi bi-hdd-network text-gray-400"></i>
                    Speicher-Last
                </h2>
                <!-- Tooltip replacing subtext -->
                <div class="group relative flex items-center cursor-help">
                    <i class="bi bi-info-circle text-gray-500 hover:text-cyan-400 transition-colors text-sm sm:text-lg hidden md:block"></i>
                    <div class="absolute left-full ml-3 top-1/2 -translate-y-1/2 z-50 w-64 bg-gray-900 border border-gray-700 p-3 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-gray-300 font-mono text-[10px] sm:text-xs">
                        Überwacht die Auslastung der physischen Server-Festplatte. Bei 100% drohen fatale Systemausfälle. Schwellenwerte dienen als Warnsystem.
                    </div>
                </div>
                
                <!-- Display overall Space in Title -->
                <div class="hidden sm:flex ml-4 rounded-full border border-gray-800 bg-gray-900/50 px-3 py-1 font-mono text-[10px] font-bold tracking-widest text-gray-400">
                    {{ $usedSpaceGb }} GB / {{ $totalSpaceGb }} GB belegt
                </div>
            </div>

            <!-- Middle/Right: Expander -->
            <div class="flex w-full md:w-auto items-center justify-between md:justify-end gap-3 sm:gap-4">
                <button @click="expanded = !expanded" class="flex items-center gap-2 bg-gray-900/80 hover:bg-gray-800 border border-gray-700 focus:outline-none focus:ring opacity-90 hover:opacity-100 transition duration-300 rounded-xl px-3 sm:px-4 py-2 shadow-inner text-gray-400 hover:text-white group">
                    <span class="text-[10px] sm:text-xs font-mono font-bold uppercase tracking-wider" x-text="expanded ? 'Schließen' : 'Details'"></span>
                    <i class="bi transform transition-transform duration-300 group-hover:scale-110" :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
            </div>
        </div>

        <!-- DER ULTIMATIVE BALKEN (IMMER SICHTBAR) -->
        <div class="relative w-full mt-6 sm:mt-8 mb-4 sm:mb-6 px-1 lg:px-6" x-data="storageCapacitySlider()" @mousemove.window="drag" @mouseup.window="stopDrag" @touchmove.window="drag" @touchend.window="stopDrag" @mouseleave.window="stopDrag">

            <!-- Balken-Container -->
            <div x-ref="track" class="w-full h-8 bg-black/60 rounded-full border border-gray-800 shadow-[inset_0_2px_15px_rgba(0,0,0,0.8)] relative overflow-visible select-none">

                <!-- Der Füllbalken -->
                <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-1000 ease-out flex items-center justify-end pr-2 overflow-hidden
                    {{ $level === 0 ? 'bg-gradient-to-r from-emerald-600 to-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.6)]' : '' }}
                    {{ $level === 1 ? 'bg-gradient-to-r from-emerald-500 via-amber-400 to-amber-500 shadow-[0_0_20px_rgba(245,158,11,0.6)]' : '' }}
                    {{ $level === 2 ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-orange-600 shadow-[0_0_20px_rgba(249,115,22,0.6)] animate-pulse' : '' }}
                    {{ $level === 3 ? 'bg-gradient-to-r from-orange-600 via-red-600 to-red-800 shadow-[0_0_30px_rgba(220,38,38,0.8)] animate-pulse' : '' }}
                    {{ $level === 4 ? 'bg-gradient-to-r from-red-600 via-red-800 to-red-950 shadow-[0_0_40px_rgba(220,38,38,0.8)] animate-pulse' : '' }}"
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
                    <div class="w-1.5 sm:w-1.5 h-full bg-amber-500/80 rounded shadow-[0_0_10px_rgba(245,158,11,0.5)] group-hover:bg-amber-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-amber-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t1"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-amber-600/70">WARNUNG 1</span>
                    </div>
                </div>

                <!-- T2: Warnung 2 -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t2}%;`" @mousedown="startDrag($event, 't2')" @touchstart.passive="startDrag($event, 't2')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-orange-500/90 rounded shadow-[0_0_10px_rgba(249,115,22,0.8)] group-hover:bg-orange-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-orange-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t2"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-orange-600/70">WARNUNG 2</span>
                    </div>
                </div>

                <!-- T3: Kritisch -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t3}%;`" @mousedown="startDrag($event, 't3')" @touchstart.passive="startDrag($event, 't3')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-rose-500/90 rounded shadow-[0_0_10px_rgba(225,29,72,0.8)] group-hover:bg-rose-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-rose-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t3"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-rose-600/70">KRITISCH</span>
                    </div>
                </div>

                <!-- 100% Festplatte Voll (Fix) -->
                <div class="absolute w-5 h-20 sm:h-18 -top-6 sm:-top-4 right-0 flex justify-end z-20 group">
                    <div class="w-1.5 h-full bg-red-600 rounded pointer-events-none shadow-[0_0_15px_rgba(220,38,38,1)]"></div>
                    <div class="absolute -top-6 sm:-top-10 right-0 text-[10px] sm:text-[12px] font-black text-red-500 font-mono text-right whitespace-nowrap drop-shadow-lg pr-2 pointer-events-none">
                        100%<br><span class="text-[7px] sm:text-[9px] uppercase tracking-wider text-red-400/90">DISK FULL</span>
                    </div>
                </div>

            </div>

            <!-- Float-Indikator des aktuellen State über dem Balken -->
            <div class="absolute z-30 transition-all duration-1000 ease-out pointer-events-none" style="left: calc({{ min($percentage, 100) }}% - 15px); top: -24px;">
                <div class="relative bg-white text-black font-black font-mono text-xs sm:text-sm px-1.5 sm:px-2 py-0.5 sm:py-1 rounded shadow-lg border-2 border-white {{ $level >= 2 ? 'animate-bounce' : '' }}">
                    {{ $percentage }}%
                    <div class="absolute w-1.5 sm:w-2 h-1.5 sm:h-2 bg-white rotate-45 -bottom-1 left-1/2 -translate-x-1/2 rounded-[1px]"></div>
                </div>
            </div>
        </div>

        <!-- MINI LOG (IMMER SICHTBAR, UNTER DEM BALKEN) -->
        <div x-show="!expanded" class="px-1 md:px-6 transition-opacity duration-300">
            @if(count($actionLog) > 0)
                <div class="text-[9px] sm:text-[10px] font-mono text-gray-500 flex gap-2 sm:gap-3 items-center truncate">
                    <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded border border-gray-700 uppercase shrink-0">Letztes Event</span>
                    <span class="text-gray-400 truncate">
                        >_ {{ $actionLog[count($actionLog)-1]['msg'] }}
                    </span>
                </div>
            @endif
        </div>

        <!-- EXPANDED VIEW -->
        <div x-show="expanded" x-collapse.duration.400ms class="pt-6 sm:pt-8 border-t border-gray-800/60 mt-1">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- One-Click-Cleaner Panel -->
                <div class="col-span-1 flex flex-col gap-5">
                    <div class="bg-gray-900/50 border border-gray-800 rounded-2xl p-4 sm:p-5 shadow-inner h-full flex flex-col">
                        <div class="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-4 border-b border-gray-800/80 pb-3 flex items-center justify-between">
                            System-Reinigung
                            <i class="bi bi-magic text-emerald-500"></i>
                        </div>
                        <div class="flex-1 flex flex-col gap-3 justify-center">
                            
                            <button wire:click="clearLaravelLogs" wire:loading.attr="disabled" title="Pfad: storage/logs/ - Leert sämtliche laravel.log Dateien, in denen sich Fehler oder Zugriffe stauen." class="w-full flex items-center justify-between bg-black border border-gray-800 hover:border-emerald-500/50 hover:bg-gray-800 transition-colors p-3 rounded-xl group relative overflow-hidden">
                                <div class="absolute inset-0 bg-emerald-500/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                <div class="flex items-center gap-3 relative z-10">
                                    <i class="bi bi-journal-x text-emerald-500 text-lg"></i>
                                    <span class="text-xs font-mono font-bold text-gray-300 group-hover:text-white transition-colors">Laravel Logs leeren</span>
                                </div>
                                <div class="flex items-center gap-2 relative z-10">
                                    <i class="bi bi-info-circle text-gray-500 hover:text-emerald-400 cursor-help transition-colors"></i>
                                    <i class="bi bi-chevron-right text-gray-600 transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </button>

                            <button wire:click="clearFrameworkCache" wire:loading.attr="disabled" title="Pfad: storage/framework/views & /cache - Erzwingt das Neuerstellen der kompilierten Blade-Sichten ohne die User-Sessions zu zerstören." class="w-full flex items-center justify-between bg-black border border-gray-800 hover:border-cyan-500/50 hover:bg-gray-800 transition-colors p-3 rounded-xl group relative overflow-hidden">
                                <div class="absolute inset-0 bg-cyan-500/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                <div class="flex items-center gap-3 relative z-10">
                                    <i class="bi bi-cpu text-cyan-500 text-lg"></i>
                                    <span class="text-xs font-mono font-bold text-gray-300 group-hover:text-white transition-colors">Views/Cache purgen</span>
                                </div>
                                <div class="flex items-center gap-2 relative z-10">
                                    <i class="bi bi-info-circle text-gray-500 hover:text-cyan-400 cursor-help transition-colors"></i>
                                    <i class="bi bi-chevron-right text-gray-600 transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </button>

                            <button wire:click="clearTempDirectory" wire:loading.attr="disabled" title="Pfad: storage/app/tmp & livewire-tmp - Löscht sämtliche Rest-Dateien von abgebrochenen Uploads oder Zwischenprozessen." class="w-full flex items-center justify-between bg-black border border-gray-800 hover:border-amber-500/50 hover:bg-gray-800 transition-colors p-3 rounded-xl group relative overflow-hidden">
                                <div class="absolute inset-0 bg-amber-500/5 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                                <div class="flex items-center gap-3 relative z-10">
                                    <i class="bi bi-trash2 text-amber-500 text-lg"></i>
                                    <span class="text-xs font-mono font-bold text-gray-300 group-hover:text-white transition-colors">Temp-Ordner leeren</span>
                                </div>
                                <div class="flex items-center gap-2 relative z-10">
                                    <i class="bi bi-info-circle text-gray-500 hover:text-amber-400 cursor-help transition-colors"></i>
                                    <i class="bi bi-chevron-right text-gray-600 transition-transform group-hover:translate-x-1"></i>
                                </div>
                            </button>

                        </div>
                    </div>
                </div>

                <!-- Ordnergrößen & Action Log -->
                <div class="col-span-1 lg:col-span-2 flex flex-col gap-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Ordnerstruktur Tabelle -->
                        <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-4 sm:p-5 flex-1 shadow-inner relative">
                            <div class="flex justify-between items-center mb-3 border-b border-gray-800/80 pb-2">
                                <div class="text-[10px] text-gray-400 uppercase font-black tracking-widest flex items-center gap-2">
                                    <i class="bi bi-folder2-open text-gray-500"></i> storage/ Ordner
                                </div>
                                <div class="text-[10px] font-mono text-gray-500">
                                    Gesamt: <span class="text-white">{{ $totalStorageAppGb }} GB</span>
                                </div>
                            </div>
                            
                            <div wire:loading wire:target="loadStorageDetails" class="absolute inset-0 z-20 bg-gray-900/80 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
                            </div>

                            <div class="space-y-1 mt-3">
                                @forelse($folderSizes as $folder)
                                    <div class="flex justify-between items-center bg-black/40 rounded p-1.5 px-3 border border-gray-800/50">
                                        <div class="text-xs font-mono text-gray-300"><i class="bi bi-folder me-2 text-emerald-600/70"></i>{{ $folder['name'] }}</div>
                                        <div class="text-[10px] font-mono font-bold {{ $folder['size_mb'] > 1000 ? 'text-red-400' : 'text-gray-400' }}">{{ $folder['size_mb'] }} MB</div>
                                    </div>
                                @empty
                                    <div class="text-[10px] text-gray-500 font-mono text-center py-4">Lade Ordner...</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Action Log Terminal -->
                        <div class="flex-1 bg-[#0a0a0c] rounded-2xl border border-gray-800/80 relative overflow-hidden flex flex-col shadow-inner">
                            <div class="absolute top-0 inset-x-0 h-7 sm:h-8 bg-gray-900 border-b border-gray-800/80 flex items-center px-3 gap-2">
                                <div class="w-2.5 h-2.5 rounded-full bg-red-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-yellow-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-green-500/50"></div>
                                <span class="ml-2 text-[8px] sm:text-[9px] uppercase tracking-widest text-gray-500 font-mono">Speicher-Log</span>
                            </div>
                            <div class="p-4 pt-10 sm:pt-12 pb-4 space-y-2.5 h-full overflow-y-auto custom-scrollbar min-h-[160px]">
                                @forelse($actionLog as $log)
                                    <div class="flex items-start gap-3 font-mono text-[10px] sm:text-xs">
                                        <div class="mt-0.5 shrink-0">
                                            @if($log['type'] === 'success') <i class="bi bi-check-circle-fill text-emerald-500"></i> @endif
                                            @if($log['type'] === 'warning') <i class="bi bi-exclamation-triangle-fill text-amber-500"></i> @endif
                                            @if($log['type'] === 'danger') <i class="bi bi-exclamation-octagon-fill text-orange-500 pulse"></i> @endif
                                            @if($log['type'] === 'critical') <i class="bi bi-shield-fill-x text-red-500 pulse"></i> @endif
                                        </div>
                                        <span class="{{ $log['type'] === 'success' ? 'text-emerald-300' : '' }} {{ $log['type'] === 'warning' ? 'text-amber-300' : '' }} {{ $log['type'] === 'danger' ? 'text-orange-300 font-bold' : '' }} {{ $log['type'] === 'critical' ? 'text-red-400 font-black tracking-wide' : '' }}">
                                            >_ {{ $log['msg'] }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="text-gray-600 text-[10px] font-mono">>_ Keine Aufzeichnungen ...</div>
                                @endforelse
                            </div>
                        </div>

                    </div>

                    <!-- Größte Dateien Details -->
                    <div class="bg-gray-900/50 border border-gray-800 rounded-2xl p-4 sm:p-5 flex-1 shadow-inner relative">
                        <div class="text-[10px] text-gray-400 uppercase font-black tracking-widest flex items-center gap-2 mb-3 border-b border-gray-800/80 pb-2">
                            <i class="bi bi-file-earmark-bar-graph text-gray-500"></i> Die 10 größten Dateien
                        </div>
                        
                        <div wire:loading wire:target="loadStorageDetails" class="absolute inset-0 z-20 bg-gray-900/80 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
                        </div>

                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[9px] uppercase tracking-widest text-gray-500 border-b border-gray-800/80">
                                        <th class="pb-2 font-medium">Dateipfad</th>
                                        <th class="pb-2 font-medium text-right">Größe (MB)</th>
                                        <th class="pb-2 font-medium text-right hidden sm:table-cell">Letzte Änd.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($largestFiles as $f)
                                        <tr class="border-b border-gray-800/40 last:border-0 hover:bg-black/20 transition-colors">
                                            <td class="py-2 text-[10px] sm:text-xs font-mono text-gray-300 truncate max-w-[150px] sm:max-w-[300px]">
                                                {{ $f['path'] }}
                                            </td>
                                            <td class="py-2 text-[10px] sm:text-xs font-mono {{ $f['size_mb'] > 50 ? 'text-red-400 font-bold' : 'text-gray-400' }} text-right">
                                                {{ $f['size_mb'] }}
                                            </td>
                                            <td class="py-2 text-[10px] font-mono text-gray-500 text-right hidden sm:table-cell">
                                                {{ $f['last_modified'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if(count($largestFiles) === 0)
                                        <tr><td colspan="3" class="text-center text-[10px] text-gray-600 py-3 font-mono">Keine Daten geladen...</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div> <!-- END EXPANDED VIEW -->

    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('storageCapacitySlider', () => ({
                t1: @entangle('threshold1'),
                t2: @entangle('threshold2'),
                t3: @entangle('threshold3'),
                activeThumb: null,
                trackWidth: 0,
                trackLeft: 0,

                startDrag(e, thumb) {
                    this.activeThumb = thumb;
                    const rect = this.$refs.track.getBoundingClientRect();
                    this.trackWidth = rect.width;
                    this.trackLeft = rect.left;
                    document.body.style.userSelect = 'none';
                    if(e.stopPropagation) e.stopPropagation();
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
                    }
                }
            }))
        })
    </script>
</div>
