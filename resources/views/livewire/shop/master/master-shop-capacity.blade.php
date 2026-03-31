<div x-data="{ expanded: false }" class="bg-black/40 border border-gray-800/60 rounded-3xl p-6 sm:p-8 backdrop-blur-md relative shadow-[0_0_20px_rgba(0,0,0,0.3)] mb-8">

    <div class="relative z-10 flex flex-col gap-6">

        <!-- COMPACT HEADER VIEW -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">

            <!-- Left: Headline & Tooltip -->
            <div class="flex items-center gap-3">
                <h2 class="text-xl sm:text-2xl font-black text-white uppercase tracking-wider font-mono flex items-center gap-3">
                    <i class="bi bi-speedometer2 text-gray-400"></i>
                    Produktions-Last
                </h2>
                <!-- Tooltip replacing subtext -->
                <div class="group relative flex items-center cursor-help">
                    <i class="bi bi-info-circle text-gray-500 hover:text-cyan-400 transition-colors text-sm sm:text-lg hidden md:block"></i>
                    <!-- Touch Info für Mobile ist nicht hided -->
                    <div class="absolute left-full ml-3 top-1/2 -translate-y-1/2 z-50 w-64 bg-gray-900 border border-gray-700 p-3 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all text-gray-300 font-mono text-[10px] sm:text-xs">
                        Überwacht die offene Backlog-Kapazität in Echtzeit. Bei hohen Werten leitet das System automatisch logistische und e-Commerce-Bremssysteme ein, um Lieferchaos zu verhindern.
                    </div>
                </div>
            </div>

            <!-- Middle/Right: Autopilot and Expander -->
            <div class="flex w-full md:w-auto items-center justify-between md:justify-end gap-3 sm:gap-4">
                <div class="flex items-center gap-3 bg-gray-900/50 border border-gray-800 rounded-xl px-3 sm:px-4 py-2 shadow-inner">
                    <label class="text-[9px] uppercase tracking-widest text-gray-400 font-bold hidden sm:block">Autopilot</label>
                    <button wire:click="toggleAutoPilot"
                        title="Wenn aktiv, greift das System vollautomatisch in die Liefereinstellungen ein."
                        class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 {{ $autoPilotEnabled ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]' : 'bg-gray-700' }}" role="switch" aria-checked="{{ $autoPilotEnabled ? 'true' : 'false' }}">
                        <span class="sr-only">Autopilot umschalten</span>
                        <span aria-hidden="true" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $autoPilotEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>

                <button @click="expanded = !expanded" class="flex items-center gap-2 bg-gray-900/80 hover:bg-gray-800 border border-gray-700 focus:outline-none focus:ring opacity-90 hover:opacity-100 transition duration-300 rounded-xl px-3 sm:px-4 py-2 shadow-inner text-gray-400 hover:text-white group">
                    <span class="text-[10px] sm:text-xs font-mono font-bold uppercase tracking-wider" x-text="expanded ? 'Schließen' : 'Details'"></span>
                    <i class="bi transform transition-transform duration-300 group-hover:scale-110" :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </button>
            </div>
        </div>

        <!-- DER ULTIMATIVE BALKEN (IMMER SICHTBAR) -->
        <div class="relative w-full mt-6 sm:mt-8 mb-4 sm:mb-6 px-1 lg:px-6" x-data="capacitySlider()" @mousemove.window="drag" @mouseup.window="stopDrag" @touchmove.window="drag" @touchend.window="stopDrag" @mouseleave.window="stopDrag">

            <!-- Balken-Container -->
            <div x-ref="track" class="w-full h-8 bg-black/60 rounded-full border border-gray-800 shadow-[inset_0_2px_15px_rgba(0,0,0,0.8)] relative overflow-visible select-none">

                <!-- Der Füllbalken -->
                <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-1000 ease-out flex items-center justify-end pr-2 overflow-hidden
                    {{ $level === 0 ? 'bg-gradient-to-r from-emerald-600 to-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.6)]' : '' }}
                    {{ $level === 1 ? 'bg-gradient-to-r from-emerald-500 via-amber-400 to-amber-500 shadow-[0_0_20px_rgba(245,158,11,0.6)]' : '' }}
                    {{ $level === 2 ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 shadow-[0_0_20px_rgba(239,68,68,0.6)] animate-pulse' : '' }}
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

                <!-- T1: Drossel 1 -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t1}%;`" @mousedown="startDrag($event, 't1')" @touchstart.passive="startDrag($event, 't1')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-amber-500/80 rounded shadow-[0_0_10px_rgba(245,158,11,0.5)] group-hover:bg-amber-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-amber-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t1"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-amber-600/70">DROSSEL 1</span>
                    </div>
                </div>

                <!-- T2: Drossel 2 -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t2}%;`" @mousedown="startDrag($event, 't2')" @touchstart.passive="startDrag($event, 't2')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-orange-500/90 rounded shadow-[0_0_10px_rgba(249,115,22,0.8)] group-hover:bg-orange-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-orange-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t2"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-orange-600/70">DROSSEL 2</span>
                    </div>
                </div>

                <!-- T3: Express Off -->
                <div class="absolute w-6 sm:w-5 h-20 sm:h-16 -top-6 sm:-top-4 -ml-3 sm:-ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t3}%;`" @mousedown="startDrag($event, 't3')" @touchstart.passive="startDrag($event, 't3')">
                    <div class="w-1.5 sm:w-1.5 h-full bg-rose-500/90 rounded shadow-[0_0_10px_rgba(225,29,72,0.8)] group-hover:bg-rose-400 transition-colors"></div>
                    <div class="absolute -top-6 sm:-top-8 text-[9px] sm:text-[10px] font-bold text-rose-500 font-mono text-center whitespace-nowrap pointer-events-none">
                        <span x-text="t3"></span>%<br><span class="text-[7px] sm:text-[8px] uppercase tracking-wider text-rose-600/70">DROSSEL 3</span>
                    </div>
                </div>

                <!-- 100% Extreme Auslastung (Fix) -->
                <div class="absolute w-5 h-20 sm:h-18 -top-6 sm:-top-4 right-0 flex justify-end z-20 group">
                    <div class="w-1.5 h-full bg-red-600 rounded pointer-events-none shadow-[0_0_15px_rgba(220,38,38,1)]"></div>
                    <div class="absolute -top-6 sm:-top-10 right-0 text-[10px] sm:text-[12px] font-black text-red-500 font-mono text-right whitespace-nowrap drop-shadow-lg pr-2 pointer-events-none">
                        100%<br><span class="text-[7px] sm:text-[9px] uppercase tracking-wider text-red-400/90">EXTREME AUSLASTUNG</span>
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
                        >_ {{ $actionLog[0]['msg'] ?? $actionLog[count($actionLog)-1]['msg'] }}
                    </span>
                </div>
            @endif
        </div>

        <!-- EXPANDED VIEW -->
        <div x-show="expanded" x-collapse.duration.400ms class="pt-6 sm:pt-8 border-t border-gray-800/60 mt-1">

            <div class="flex flex-col lg:flex-row gap-6">

                <!-- Settings Panel -->
                <div class="flex-none w-full lg:w-48 xl:w-56 flex flex-col gap-6">
                    <div class="bg-gray-900/50 border border-gray-800 rounded-2xl p-4 sm:p-5 shadow-inner">
                        <div class="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-4 border-b border-gray-800/80 pb-3 flex items-center justify-between">
                            Experten-Setting
                            <i class="bi bi-gear-fill text-gray-600"></i>
                        </div>
                        <div class="flex flex-row sm:flex-row lg:flex-col gap-4 sm:gap-6 lg:gap-5 flex-wrap">
                            <div class="flex flex-col flex-1 min-w-[30%]">
                                <label class="text-[8px] sm:text-[9px] uppercase tracking-widest text-gray-500 font-bold mb-1.5">Arbeitszeit</label>
                                <div class="flex items-center w-full">
                                    <input type="number" step="0.5" wire:model.blur="dailyWorkingHours" class="w-full min-w-[50px] bg-black/40 border border-gray-700/50 rounded-l shadow-inner focus:border-cyan-500 focus:ring focus:ring-cyan-500/20 text-white text-xs p-2 font-mono text-center outline-none">
                                    <span class="bg-gray-800 border border-l-0 border-gray-700/50 rounded-r text-[10px] text-gray-400 px-2 sm:px-3 py-2">Std</span>
                                </div>
                            </div>

                            <div class="flex flex-col flex-1 min-w-[30%]">
                                <label class="text-[8px] sm:text-[9px] uppercase tracking-widest text-gray-500 font-bold mb-1.5">Dauer/Order</label>
                                <div class="flex items-center w-full">
                                    <input type="number" wire:model.blur="minutesPerOrder" class="w-full min-w-[50px] bg-black/40 border border-gray-700/50 rounded-l shadow-inner focus:border-cyan-500 focus:ring focus:ring-cyan-500/20 text-white text-xs p-2 font-mono text-center outline-none">
                                    <span class="bg-gray-800 border border-l-0 border-gray-700/50 rounded-r text-[10px] text-gray-400 px-2 sm:px-3 py-2">Min</span>
                                </div>
                            </div>

                            <div class="flex flex-col flex-1 min-w-[30%]">
                                <label class="text-[8px] sm:text-[9px] uppercase tracking-widest text-gray-500 font-bold mb-1.5">Support-Puffer</label>
                                <div class="flex items-center w-full">
                                    <input type="number" wire:model.blur="capacityBuffer" class="w-full min-w-[50px] bg-black/40 border border-gray-700/50 rounded-l shadow-inner focus:border-red-500 focus:ring focus:ring-red-500/20 text-red-400 text-xs p-2 font-mono text-center outline-none">
                                    <span class="bg-gray-800 border border-l-0 border-gray-700/50 rounded-r text-[10px] text-gray-400 px-2 sm:px-3 py-2">Min</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-6 text-[10px] sm:text-[11px] text-gray-400 font-mono opacity-80 border-t border-gray-800/80 pt-3">
                            <i class="bi bi-box-seam me-1 text-gray-500"></i> Max. Output errechnet: <br>
                            <strong class="text-white text-sm tracking-wider">{{ $maxCapacity }} Pakete / Tag</strong>
                        </div>
                    </div>
                </div>

                <!-- Metric Mini-Cards & Log -->
                <div class="flex-1 flex flex-col gap-5 sm:gap-6">
                    <div class="flex flex-row gap-3 sm:gap-4">
                        <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-4 sm:p-5 flex-1 relative overflow-hidden group">
                            <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                                <i class="bi bi-boxes text-8xl text-white"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="text-[9px] sm:text-[10px] uppercase text-gray-500 font-black tracking-widest truncate">Gewichtete Last</div>
                                <div class="text-2xl sm:text-3xl font-mono font-black text-white leading-none mt-2">{{ $activeOrders }}</div>
                                <div class="text-[8px] text-gray-600 uppercase tracking-wider mt-2 leading-tight hidden sm:block">
                                    Tracking: Offene Bestellungen inkl. Produktionszeit als Paket-Äquivalent
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-4 sm:p-5 flex-1 relative overflow-hidden group">
                            <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                                <i class="bi bi-activity text-8xl text-white"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="text-[9px] sm:text-[10px] uppercase text-gray-500 font-black tracking-widest truncate">Aktuelles Level</div>
                                <div class="mt-2">
                                    @if($level === 0) <span class="text-emerald-500 font-black font-mono text-lg sm:text-2xl drop-shadow-md">0 (Normal)</span> @endif
                                    @if($level === 1) <span class="text-amber-500 font-black font-mono text-lg sm:text-2xl drop-shadow-md">1 (Erhöht)</span> @endif
                                    @if($level === 2) <span class="text-orange-500 font-black font-mono text-lg sm:text-2xl drop-shadow-md">2 (Streng)</span> @endif
                                    @if($level === 3) <span class="text-rose-500 font-black font-mono text-lg sm:text-2xl drop-shadow-md">3 (Express Off)</span> @endif
                                    @if($level === 4) <span class="text-red-600 font-black font-mono text-lg sm:text-2xl drop-shadow-md">4 (Extreme)</span> @endif
                                </div>
                                <div class="text-[8px] text-gray-600 uppercase tracking-wider mt-2 leading-tight hidden sm:block">
                                    Dynamisch generierte Stufe durch den Autopilot.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Log Terminal -->
                    <div class="flex-1 min-h-[160px] sm:min-h-[200px] bg-[#0a0a0c] rounded-2xl border border-gray-800/80 relative overflow-hidden flex flex-col shadow-inner">
                        <div class="absolute top-0 inset-x-0 h-7 sm:h-8 bg-gray-900 border-b border-gray-800/80 flex items-center px-3 gap-2">
                            <div class="w-2.5 h-2.5 rounded-full bg-red-500/50"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-yellow-500/50"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-green-500/50"></div>
                            <span class="ml-2 text-[8px] sm:text-[9px] uppercase tracking-widest text-gray-500 font-mono">Produktions-Last-Log</span>
                        </div>
                        <div class="p-4 pt-10 sm:pt-12 pb-4 space-y-2.5 h-full overflow-y-auto custom-scrollbar">
                            @forelse($actionLog as $log)
                                <div class="flex items-start gap-3 font-mono text-[10px] sm:text-xs">
                                    <div class="mt-0.5 shrink-0">
                                        @if($log['type'] === 'success') <i class="bi bi-check-circle-fill text-emerald-500"></i> @endif
                                        @if($log['type'] === 'info') <i class="bi bi-info-circle-fill text-cyan-500"></i> @endif
                                        @if($log['type'] === 'warning') <i class="bi bi-exclamation-triangle-fill text-amber-500"></i> @endif
                                        @if($log['type'] === 'danger') <i class="bi bi-exclamation-octagon-fill text-orange-500 pulse"></i> @endif
                                        @if($log['type'] === 'critical') <i class="bi bi-shield-fill-x text-red-500 pulse"></i> @endif
                                        @if($log['type'] === 'system') <i class="bi bi-robot text-purple-400"></i> @endif
                                        @if($log['type'] === 'suggest') <i class="bi bi-lightbulb-fill text-yellow-500"></i> @endif
                                    </div>
                                    <span class="
                                        {{ $log['type'] === 'system' ? 'text-purple-300' : '' }}
                                        {{ $log['type'] === 'success' ? 'text-emerald-300' : '' }}
                                        {{ $log['type'] === 'info' ? 'text-gray-300' : '' }}
                                        {{ $log['type'] === 'warning' ? 'text-amber-300' : '' }}
                                        {{ $log['type'] === 'danger' ? 'text-orange-300 font-bold' : '' }}
                                        {{ $log['type'] === 'critical' ? 'text-red-400 font-black tracking-wide' : '' }}
                                        {{ $log['type'] === 'suggest' ? 'text-yellow-200/80' : '' }}
                                    ">
                                        >_ {{ $log['msg'] }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-gray-600 text-[10px] font-mono">>_ Keine Aufzeichnungen ...</div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </div> <!-- END EXPANDED VIEW -->

    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('capacitySlider', () => ({
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
                    // Disable text selection and ensure Livewire doesn't interfere while dragging
                    document.body.style.userSelect = 'none';
                    // Stop event propagation to prevent triggering drag from parents
                    if(e.stopPropagation) e.stopPropagation();
                },

                drag(e) {
                    if (!this.activeThumb) return;
                    let clientX = e.clientX || (e.touches && e.touches[0].clientX);
                    if (!clientX) return;

                    let percent = ((clientX - this.trackLeft) / this.trackWidth) * 100;
                    percent = Math.max(0, Math.min(100, Math.round(percent)));

                    if (this.activeThumb === 't1') {
                        this.t1 = Math.min(percent, this.t2 - 2); // 2% minimum distance
                    } else if (this.activeThumb === 't2') {
                        this.t2 = Math.max(this.t1 + 2, Math.min(percent, this.t3 - 2));
                    } else if (this.activeThumb === 't3') {
                        this.t3 = Math.max(this.t2 + 2, Math.min(percent, 99)); // keep it under 100
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
