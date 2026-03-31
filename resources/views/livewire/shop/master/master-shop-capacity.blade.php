<div class="bg-black/40 border border-gray-800/60 rounded-3xl p-6 sm:p-8 backdrop-blur-md relative overflow-hidden shadow-[0_0_20px_rgba(0,0,0,0.3)] mb-8">

    <!-- Hintergrund-Glühen abhängig vom Level -->
    <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full blur-3xl opacity-20 pointer-events-none transition-colors duration-1000
        {{ $level === 0 ? 'bg-emerald-500' : ($level === 1 ? 'bg-amber-500' : ($level === 2 ? 'bg-orange-500' : 'bg-red-600')) }}">
    </div>

    <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center mb-14 gap-4">
        <div>
            <h2 class="text-3xl font-black text-white uppercase tracking-wider font-mono flex items-center gap-3">
                <i class="bi bi-speedometer2 text-gray-400"></i>
                Produktions-Last
            </h2>
            <p class="text-gray-400 font-mono text-xs max-w-lg mt-1 leading-relaxed">
                Überwacht die offene Backlog-Kapazität in Echtzeit. Bei hohen Werten leitet das System automatisch logistische und e-Commerce-Bremssysteme ein, um Lieferchaos zu verhindern.
            </p>
        </div>

        <div class="flex items-center gap-4 bg-gray-900/50 border border-gray-800 rounded-2xl p-3 px-5 shadow-inner">
            <div class="flex flex-col gap-2 relative">
                <div class="flex items-center gap-3">
                    <div class="flex flex-col">
                        <label class="text-[8px] uppercase tracking-widest text-gray-500 font-bold mb-1">Arbeitszeit</label>
                        <div class="flex items-center">
                            <input type="number" step="0.5" wire:model.blur="dailyWorkingHours" class="w-14 bg-black/40 border border-gray-700/50 rounded-l pointer-events-auto shadow-inner focus:border-cyan-500 focus:ring focus:ring-cyan-500/20 text-white text-xs p-1 font-mono text-center">
                            <span class="bg-gray-800 border border-l-0 border-gray-700/50 rounded-r text-[10px] text-gray-400 px-1.5 py-1">Std</span>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[8px] uppercase tracking-widest text-gray-500 font-bold mb-1">Dauer/Order</label>
                        <div class="flex items-center">
                            <input type="number" wire:model.blur="minutesPerOrder" class="w-14 bg-black/40 border border-gray-700/50 rounded-l pointer-events-auto shadow-inner focus:border-cyan-500 focus:ring focus:ring-cyan-500/20 text-white text-xs p-1 font-mono text-center">
                            <span class="bg-gray-800 border border-l-0 border-gray-700/50 rounded-r text-[10px] text-gray-400 px-1.5 py-1">Min</span>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[8px] uppercase tracking-widest text-gray-500 font-bold mb-1">Support-Puffer</label>
                        <div class="flex items-center">
                            <input type="number" wire:model.blur="capacityBuffer" class="w-14 bg-black/40 border border-gray-700/50 rounded-l pointer-events-auto shadow-inner focus:border-cyan-500 focus:ring focus:ring-cyan-500/20 text-white text-xs p-1 font-mono text-center text-red-400">
                            <span class="bg-gray-800 border border-l-0 border-gray-700/50 rounded-r text-[10px] text-gray-400 px-1.5 py-1">Abzug</span>
                        </div>
                    </div>
                </div>
                <!-- Theoretical info display -->
                <div class="absolute -bottom-6 left-0 text-[10px] text-gray-400 font-mono opacity-80 whitespace-nowrap">
                    Ergibt 100% Limit: <strong class="text-white">{{ $maxCapacity }} Pakete / Tag</strong>
                </div>
            </div>

            <div class="w-px h-10 bg-gray-800 mx-1"></div>

            <div class="flex flex-col items-center justify-center">
                <label class="text-[9px] uppercase tracking-widest text-gray-500 font-bold mb-2">Autopilot</label>
                <button wire:click="toggleAutoPilot"
                    title="Wenn aktiv, greift das System vollautomatisch in die Liefereinstellungen ein."
                    class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-gray-900 {{ $autoPilotEnabled ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]' : 'bg-gray-700' }}" role="switch" aria-checked="{{ $autoPilotEnabled ? 'true' : 'false' }}">
                    <span class="sr-only">Autopilot umschalten</span>
                    <span aria-hidden="true" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $autoPilotEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- DER ULTIMATIVE BALKEN -->
    <div class="relative w-full mt-4 mb-20 px-2 lg:px-8" x-data="capacitySlider()" @mousemove.window="drag" @mouseup.window="stopDrag" @touchmove.window="drag" @touchend.window="stopDrag" @mouseleave.window="stopDrag">

        <!-- Balken-Container -->
        <div x-ref="track" class="w-full h-8 bg-black/60 rounded-full border border-gray-800 shadow-[inset_0_2px_15px_rgba(0,0,0,0.8)] relative overflow-visible select-none">

                <!-- Der Füllbalken -->
            <div class="absolute top-0 left-0 h-full rounded-full transition-all duration-1000 ease-out flex items-center justify-end pr-2 overflow-hidden
                {{ $level === 0 ? 'bg-gradient-to-r from-emerald-600 to-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.6)]' : '' }}
                {{ $level === 1 ? 'bg-gradient-to-r from-emerald-500 via-amber-400 to-amber-500 shadow-[0_0_20px_rgba(245,158,11,0.6)]' : '' }}
                {{ $level === 2 ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 shadow-[0_0_20px_rgba(239,68,68,0.6)] animate-pulse' : '' }}
                {{ $level >= 3 ? 'bg-gradient-to-r from-orange-600 via-red-600 to-red-800 shadow-[0_0_30px_rgba(220,38,38,0.8)] animate-pulse' : '' }}"
                style="width: {{ min($percentage, 100) }}%;">

                <!-- Glanz-Effekt im Balken -->
                <div class="absolute inset-0 bg-gradient-to-b from-white/20 to-transparent pointer-events-none"></div>

                <!-- Animierter Streifen (Pulsieren/Bewegen) für High-Load -->
                @if($level >= 2)
                <div class="absolute inset-0 opacity-30" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,0.3) 10px, rgba(0,0,0,0.3) 20px);"></div>
                @endif
            </div>

            <!-- T1: Drossel 1 -->
            <div class="absolute w-5 h-16 -top-4 -ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t1}%;`" @mousedown="startDrag($event, 't1')" @touchstart.passive="startDrag($event, 't1')">
                <div class="w-1.5 h-full bg-amber-500/80 rounded shadow-[0_0_10px_rgba(245,158,11,0.5)] group-hover:bg-amber-400 transition-colors"></div>
                <div class="absolute -top-8 text-[10px] font-bold text-amber-500 font-mono text-center whitespace-nowrap pointer-events-none">
                    <span x-text="t1"></span>%<br><span class="text-[8px] uppercase tracking-wider text-amber-600/70">DROSSEL 1</span>
                </div>
                <!-- Tooltip (On Hover) -->
                <div class="absolute top-16 flex items-center justify-center p-1 bg-amber-900/60 border border-amber-500/50 rounded shadow-md w-max pointer-events-none opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                     <span class="text-[9px] uppercase tracking-widest text-amber-400 font-bold whitespace-nowrap">Lieferzeit: Gelb</span>
                </div>
            </div>

            <!-- T2: Drossel 2 -->
            <div class="absolute w-5 h-16 -top-4 -ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t2}%;`" @mousedown="startDrag($event, 't2')" @touchstart.passive="startDrag($event, 't2')">
                <div class="w-1.5 h-full bg-orange-500/90 rounded shadow-[0_0_10px_rgba(249,115,22,0.8)] group-hover:bg-orange-400 transition-colors"></div>
                <div class="absolute -top-8 text-[10px] font-bold text-orange-500 font-mono text-center whitespace-nowrap pointer-events-none">
                    <span x-text="t2"></span>%<br><span class="text-[8px] uppercase tracking-wider text-orange-600/70">DROSSEL 2</span>
                </div>
                <!-- Tooltip (On Hover) -->
                <div class="absolute top-16 flex items-center justify-center p-1 bg-orange-900/60 border border-orange-500/50 rounded shadow-md w-max pointer-events-none opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                     <span class="text-[9px] uppercase tracking-widest text-orange-400 font-bold whitespace-nowrap">Lieferzeit: Rot</span>
                </div>
            </div>

            <!-- T3: Express Off -->
            <div class="absolute w-5 h-16 -top-4 -ml-2.5 flex justify-center z-20 cursor-ew-resize group" :style="`left: ${t3}%;`" @mousedown="startDrag($event, 't3')" @touchstart.passive="startDrag($event, 't3')">
                <div class="w-1.5 h-full bg-rose-500/90 rounded shadow-[0_0_10px_rgba(225,29,72,0.8)] group-hover:bg-rose-400 transition-colors"></div>
                <div class="absolute -top-8 text-[10px] font-bold text-rose-500 font-mono text-center whitespace-nowrap pointer-events-none">
                    <span x-text="t3"></span>%<br><span class="text-[8px] uppercase tracking-wider text-rose-600/70">DROSSEL 3</span>
                </div>
                <!-- Tooltip (On Hover) -->
                <div class="absolute top-16 flex items-center justify-center p-1 bg-rose-900/60 border border-rose-500/50 rounded shadow-md w-max pointer-events-none opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                     <span class="text-[9px] uppercase tracking-widest text-rose-400 font-bold whitespace-nowrap">Express OFF</span>
                </div>
            </div>

            <!-- 100% Lockdown (Fix) -->
            <!-- Group wrapper hinzugefügt und pointer-events-none vom wrapper entfernt um hovern zu ermöglichen -->
            <div class="absolute w-5 h-18 -top-4 right-0 flex justify-end z-20 group">
                <!-- Der Rote Pin -->
                <div class="w-1.5 h-full bg-red-600 rounded pointer-events-none shadow-[0_0_15px_rgba(220,38,38,1)]"></div>
                <div class="absolute -top-10 right-0 text-[12px] font-black text-red-500 font-mono text-right whitespace-nowrap drop-shadow-lg pr-2 pointer-events-none">
                    100%<br><span class="text-[9px] uppercase tracking-wider text-red-400/90">LOCKDOWN</span>
                </div>
                <!-- Tooltip (On Hover) -->
                <div class="absolute top-[3.5rem] right-0 flex items-center justify-center p-1 bg-red-900/80 border border-red-500/80 rounded shadow-[0_0_10px_rgba(220,38,38,0.8)] w-max mr-2 mt-2 pointer-events-none opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                     <span class="text-[9px] uppercase tracking-widest text-red-300 font-bold whitespace-nowrap">Checkout Deaktiviert</span>
                </div>
            </div>

        </div>

        <!-- Float-Indikator des aktuellen State über dem Balken -->
        <div class="absolute z-30 transition-all duration-1000 ease-out pointer-events-none" style="left: calc({{ min($percentage, 100) }}% - 20px); top: -20px;">
            <div class="relative bg-white text-black font-black font-mono text-sm px-2 py-1 rounded shadow-lg border-2 border-white
                {{ $level >= 2 ? 'animate-bounce' : '' }}">
                {{ $percentage }}%
                <div class="absolute w-2 h-2 bg-white rotate-45 -bottom-1 left-1/2 -translate-x-1/2 rounded-[1px]"></div>
            </div>
        </div>
    </div>

    <!-- Action Log & Live Status Panel -->
    <div class="bg-black/50 border border-gray-800/80 rounded-2xl p-5 shadow-inner mt-24">

        <div class="flex flex-col md:flex-row gap-6">
            <!-- Metric Mini-Cards -->
            <div class="flex-none flex flex-row md:flex-col gap-3">
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl p-3 px-5 text-center flex-1 flex flex-col justify-center">
                    <div class="text-[10px] uppercase text-gray-500 font-bold tracking-widest">Offene Bestellungen</div>
                    <div class="text-2xl font-mono font-black text-white leading-none mt-1">{{ $activeOrders }}</div>
                    <div class="text-[8px] text-gray-600 uppercase tracking-wider mt-1">(Processing / Pending)</div>
                </div>
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl p-3 px-5 text-center flex-1 flex flex-col justify-center">
                    <div class="text-[10px] uppercase text-gray-500 font-bold tracking-widest">Aktuelles Level</div>
                    <div>
                        @if($level === 0) <span class="text-emerald-500 font-black font-mono text-xl drop-shadow-md">0 (Normal)</span> @endif
                        @if($level === 1) <span class="text-amber-500 font-black font-mono text-xl drop-shadow-md">1 (Erhöht)</span> @endif
                        @if($level === 2) <span class="text-orange-500 font-black font-mono text-xl drop-shadow-md">2 (Streng)</span> @endif
                        @if($level === 3) <span class="text-rose-500 font-black font-mono text-xl drop-shadow-md">3 (Express Off)</span> @endif
                        @if($level === 4) <span class="text-red-600 font-black font-mono text-xl drop-shadow-md">4 (Lockdown)</span> @endif
                    </div>
                </div>
            </div>

            <!-- Action Log Terminal -->
            <div class="flex-1 bg-black rounded-xl border border-gray-800/80 relative overflow-hidden flex flex-col justify-end">
                <div class="absolute top-0 inset-x-0 h-6 bg-gray-900/90 border-b border-gray-800/80 flex items-center px-3 gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-red-500/50"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-yellow-500/50"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-green-500/50"></div>
                    <span class="ml-2 text-[9px] uppercase tracking-widest text-gray-600 font-mono">System.Logic.Log</span>
                </div>

                <div class="p-4 pt-8 space-y-2 h-full flex flex-col justify-center">
                    @foreach($actionLog as $log)
                        <div class="flex items-start gap-3 font-mono text-xs">
                            <div class="mt-0.5">
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
                    @endforeach
                </div>
            </div>
        </div>
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
