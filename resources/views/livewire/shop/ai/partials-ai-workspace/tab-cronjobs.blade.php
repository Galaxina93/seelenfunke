<div wire:key="tab-cronjobs" class="flex-1 shrink-0 rounded-2xl border border-[var(--theme-color-50)] bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-[0_0_30px_var(--theme-color-20)] h-full w-full p-4 sm:p-6">
<div class="h-full flex flex-col space-y-6 overflow-y-auto custom-scrollbar">
    <!-- Header -->
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-100 to-gray-500 flex items-center gap-2 sm:gap-3">
                <x-heroicon-o-clock class="w-8 h-8 text-[var(--theme-color-40)]" />
                Server Cronjobs
            </h2>
            <p class="text-sm text-gray-400 mt-1 max-w-2xl">
                Verwalte und überwache hier alle automatisierten Hintergrundprozesse (Cronjobs). Du kannst Jobs temporär pausieren oder sie manuell sofort anstoßen, um Fehler zu debuggen.
            </p>
        </div>
        <div class="hidden sm:flex items-center gap-2">
            <div class="px-3 py-1.5 rounded-full bg-green-500/10 border border-green-500/30 text-green-400 text-xs font-medium flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                Scheduler aktiv
            </div>
        </div>
    </div>

    <!-- Table Layout of Cronjobs -->
    <div class="flex flex-col gap-3 pb-4">
        <!-- Table Header (hidden on mobile) -->
        <div class="hidden lg:grid grid-cols-12 gap-4 px-6 py-3 text-gray-500 text-[10px] sm:text-xs uppercase tracking-widest bg-gray-900/40 rounded-xl font-bold border border-gray-800/50">
            <div class="col-span-3">Cronjob & Info</div>
            <div class="col-span-3">Befehl & Intervall</div>
            <div class="col-span-3">Status & Letzter Lauf</div>
            <div class="col-span-3 text-right">Aktionen</div>
        </div>

        @foreach($this->cronjobs as $job)
            @php
                $nextRun = $job->next_run_at;
                $lastRun = $job->last_run_at ?? $job->previous_run_at;
            @endphp
            <div class="relative bg-gray-900/50 backdrop-blur-md rounded-xl border border-gray-800 hover:border-[var(--theme-color-50)] transition-all duration-300 overflow-hidden group shadow-xl">
                
                <!-- Status Line indicator -->
                <div class="absolute top-0 left-0 w-1.5 h-full transition-colors duration-500 {{ !$job->is_active ? 'bg-gray-500' : ($job->status === 'success' ? 'bg-green-500' : ($job->status === 'error' ? 'bg-red-500' : 'bg-[var(--theme-color)]')) }}"></div>
                
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 p-4 pl-6 items-center relative z-10">
                    
                    <!-- Title & Description -->
                    <div class="col-span-1 lg:col-span-3">
                        <h3 class="text-sm sm:text-base font-bold text-gray-200 group-hover:text-white transition-colors flex items-center gap-2">
                            {{ $job->name }}
                        </h3>
                        <p class="text-[10px] sm:text-xs text-gray-400 mt-1 line-clamp-2" title="{{ $job->description }}">
                            {{ $job->description }}
                        </p>
                    </div>

                    <!-- Command & Schedule -->
                    <div class="col-span-1 lg:col-span-3 flex flex-col gap-2">
                        <div>
                            <code class="text-[10px] sm:text-xs text-[var(--theme-color-60)] bg-[var(--theme-color-10)] px-2 py-0.5 rounded font-mono break-all">{{ $job->command }}</code>
                        </div>
                        
                        @if($editingJobId === $job->id)
                            <!-- Edit Mode -->
                            <div class="flex items-center gap-2">
                                <input type="text" wire:model="editingSchedule" 
                                       class="bg-black border border-gray-700 text-white text-[10px] sm:text-xs font-mono rounded focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] w-full p-1" 
                                       placeholder="z.B. everyMinute">
                                <button wire:click="saveSchedule" class="text-green-400 hover:text-green-300 p-1"><x-heroicon-o-check class="w-4 h-4" /></button>
                                <button wire:click="cancelEdit" class="text-gray-500 hover:text-white p-1"><x-heroicon-o-x-mark class="w-4 h-4" /></button>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[10px] sm:text-xs text-gray-300 bg-gray-800 px-1.5 sm:px-2 py-0.5 rounded truncate">{{ $job->schedule }}</span>
                                <button wire:click="editSchedule('{{ $job->id }}')" class="text-gray-500 hover:text-[var(--theme-color)] transition-colors p-1" title="Intervall bearbeiten">
                                    <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Last Run & Status -->
                    <div class="col-span-1 lg:col-span-3 flex flex-col gap-1 text-[10px] sm:text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-500">Zuletzt:</span>
                            <span class="{{ $job->status === 'success' ? 'text-green-400' : ($job->status === 'error' ? 'text-red-400' : 'text-gray-400') }} font-bold">
                                {{ $job->last_run_at ? $job->last_run_at->diffForHumans() : 'Noch nie' }}
                            </span>
                        </div>
                        @if($job->is_active && $nextRun && $lastRun)
                            <div class="flex items-center gap-1.5" x-data="{
                                nextRunTs: {{ $nextRun->timestamp * 1000 }},
                                countdown: '',
                                init() { setInterval(() => this.update(), 1000); this.update(); },
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
                                <span class="text-gray-500">Nächster in:</span>
                                <span class="text-[color:var(--theme-color)] font-mono" x-text="countdown"></span>
                            </div>
                        @elseif(!$job->is_active)
                            <div class="flex items-center gap-1.5">
                                <span class="text-gray-500">Status:</span>
                                <span class="text-gray-500 font-bold">Pausiert</span>
                            </div>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="col-span-1 lg:col-span-3 flex items-center justify-end gap-3 sm:gap-4 mt-2 lg:mt-0">
                        <button wire:click="runNow('{{ $job->id }}')" 
                                wire:loading.attr="disabled"
                                class="py-1.5 px-3 rounded-lg text-[10px] sm:text-xs font-semibold uppercase tracking-wider transition-all duration-300 flex items-center gap-2
                                {{ $job->is_active ? 'bg-gray-800 hover:bg-[var(--theme-color-20)] text-gray-300 hover:text-[var(--theme-color)] border border-gray-700 hover:border-[var(--theme-color-50)]' : 'bg-gray-900 text-gray-600 cursor-not-allowed border border-gray-800' }}"
                                {{ !$job->is_active ? 'disabled' : '' }}>
                            
                            <span wire:loading.remove wire:target="runNow('{{ $job->id }}')" class="flex items-center gap-1.5">
                                <x-heroicon-o-play class="w-3.5 h-3.5" />
                                <span class="hidden sm:inline">Ausführen</span>
                            </span>
                            
                            <span wire:loading wire:target="runNow('{{ $job->id }}')" class="flex items-center gap-1.5 text-[color:var(--theme-color)]">
                                <svg class="animate-spin h-3.5 w-3.5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="hidden sm:inline">Läuft...</span>
                            </span>
                        </button>

                        <!-- Toggle Switch -->
                        <button wire:click="toggleCronjob('{{ $job->id }}')" 
                                class="relative inline-flex h-5 w-9 sm:h-6 sm:w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)] focus:ring-offset-2 focus:ring-offset-gray-900 {{ $job->is_active ? 'bg-[var(--theme-color)]' : 'bg-gray-700' }}">
                            <span class="pointer-events-none inline-block h-4 w-4 sm:h-5 sm:w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $job->is_active ? 'translate-x-4 sm:translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>

                </div>

                <!-- Full Width Progress Bar (Bottom) -->
                @if($job->is_active && $nextRun && $lastRun)
                <div class="absolute bottom-0 left-0 w-full h-1 bg-gray-950/50" 
                     x-data="{
                        nextRunTs: {{ $nextRun->timestamp * 1000 }},
                        lastRunTs: {{ $lastRun->timestamp * 1000 }},
                        progress: 0,
                        init() { setInterval(() => this.update(), 1000); this.update(); },
                        update() {
                            let total = this.nextRunTs - this.lastRunTs;
                            let current = Date.now() - this.lastRunTs;
                            if(total <= 0) this.progress = 100;
                            else if(current < 0) this.progress = 0;
                            else if(current >= total) this.progress = 100;
                            else this.progress = (current / total) * 100;
                        }
                     }">
                    <div class="h-full bg-gradient-to-r from-[color:var(--theme-color)] to-[color:var(--theme-color-50)] transition-all duration-1000 ease-linear shadow-[0_0_10px_var(--theme-color-50)]" :style="`width: ${progress}%`"></div>
                </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
</div>
