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

    <!-- Grid of Cronjobs -->
    <div class="grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-6">
        @foreach($this->cronjobs as $job)
            <div class="group relative bg-gray-900/50 backdrop-blur-md rounded-2xl p-4 sm:p-6 border border-gray-800 hover:border-[var(--theme-color-50)] transition-all duration-300 shadow-xl overflow-hidden flex flex-col h-full">
                
                <!-- Status LED Blur Effect -->
                <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full opacity-20 blur-3xl transition-colors duration-500
                    {{ !$job->is_active ? 'bg-gray-500' : ($job->status === 'success' ? 'bg-green-500' : ($job->status === 'error' ? 'bg-red-500' : 'bg-[var(--theme-color)]')) }}">
                </div>

                <div class="relative flex-1 flex flex-col">
                    <!-- Title & Toggle -->
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1 pr-4">
                            <h3 class="text-lg font-bold text-gray-200 group-hover:text-white transition-colors flex items-center gap-2">
                                {{ $job->name }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <code class="text-xs text-[var(--theme-color-60)] bg-[var(--theme-color-10)] px-2 py-0.5 rounded font-mono">{{ $job->command }}</code>
                            </div>
                        </div>
                        
                        <!-- Toggle Switch -->
                        <button wire:click="toggleCronjob('{{ $job->id }}')" 
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)] focus:ring-offset-2 focus:ring-offset-gray-900 {{ $job->is_active ? 'bg-[var(--theme-color)]' : 'bg-gray-700' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $job->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>

                    <!-- Description -->
                    <p class="text-sm text-gray-400 mb-6 flex-1">
                        {{ $job->description }}
                    </p>

                    <!-- Meta Data Footer -->
                    <div class="mt-auto space-y-3 pt-4 border-t border-gray-800/50">
                        <!-- Schedule Info -->
                        @if($editingJobId === $job->id)
                            <div class="flex flex-col gap-2 bg-gray-900 p-3 rounded-xl border border-[var(--theme-color-50)] shadow-inner relative">
                                <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                                    <span>Intervall bearbeiten:</span>
                                    <button wire:click="cancelEdit" class="text-gray-500 hover:text-white"><x-heroicon-o-x-mark class="w-4 h-4" /></button>
                                </div>
                                <input type="text" wire:model="editingSchedule" 
                                       class="bg-black border border-gray-700 text-white text-xs font-mono rounded focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] w-full p-2" 
                                       placeholder="z.B. everyMinute oder 0 8 * * *">
                                
                                <!-- Suggestions -->
                                <div class="flex flex-wrap gap-1.5 mt-1">
                                    <button wire:click="$set('editingSchedule', 'everyMinute')" class="text-[9px] bg-gray-800 hover:bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded border border-gray-700">everyMinute</button>
                                    <button wire:click="$set('editingSchedule', 'everyFiveMinutes')" class="text-[9px] bg-gray-800 hover:bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded border border-gray-700">everyFiveMinutes</button>
                                    <button wire:click="$set('editingSchedule', 'hourly')" class="text-[9px] bg-gray-800 hover:bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded border border-gray-700">hourly</button>
                                    <button wire:click="$set('editingSchedule', 'daily')" class="text-[9px] bg-gray-800 hover:bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded border border-gray-700">daily</button>
                                    <button wire:click="$set('editingSchedule', '0 8 * * *')" class="text-[9px] bg-gray-800 hover:bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded border border-gray-700 font-mono">0 8 * * *</button>
                                </div>
                                
                                <button wire:click="saveSchedule" class="mt-2 w-full py-1.5 bg-[var(--theme-color-20)] text-[var(--theme-color)] text-xs font-bold rounded hover:bg-[var(--theme-color-30)] transition-colors flex items-center justify-center gap-1">
                                    <x-heroicon-o-check class="w-3.5 h-3.5" /> Speichern
                                </button>
                            </div>
                        @else
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500 flex items-center gap-1.5 shrink-0">
                                    <x-heroicon-o-calendar class="w-3.5 h-3.5" />
                                    Intervall:
                                </span>
                                <div class="flex items-center gap-1.5 sm:gap-2">
                                    <span class="font-mono text-[10px] sm:text-xs text-gray-300 bg-gray-800 px-1.5 sm:px-2 py-0.5 rounded truncate max-w-[120px] sm:max-w-none">{{ $job->schedule }}</span>
                                    <button wire:click="editSchedule('{{ $job->id }}')" class="text-gray-500 hover:text-[var(--theme-color)] transition-colors p-1" title="Intervall bearbeiten">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        @endif

                        <!-- Last Run Info -->
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500 flex items-center gap-1.5">
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                Letzter Lauf:
                            </span>
                            <span class="{{ $job->status === 'success' ? 'text-green-400' : ($job->status === 'error' ? 'text-red-400' : 'text-gray-400') }}">
                                {{ $job->last_run_at ? $job->last_run_at->diffForHumans() : 'Noch nie' }}
                            </span>
                        </div>
                        
                        <!-- Run Now Button -->
                        <div class="pt-2">
                            <button wire:click="runNow('{{ $job->id }}')" 
                                    wire:loading.attr="disabled"
                                    class="w-full py-2 px-4 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all duration-300 flex justify-center items-center gap-2
                                    {{ $job->is_active ? 'bg-gray-800 hover:bg-[var(--theme-color-20)] text-gray-300 hover:text-[var(--theme-color)] border border-gray-700 hover:border-[var(--theme-color-50)]' : 'bg-gray-900 text-gray-600 cursor-not-allowed border border-gray-800' }}"
                                    {{ !$job->is_active ? 'disabled' : '' }}>
                                
                                <span wire:loading.remove wire:target="runNow('{{ $job->id }}')" class="flex items-center gap-2">
                                    <x-heroicon-o-play class="w-4 h-4" />
                                    Jetzt Ausführen
                                </span>
                                
                                <span wire:loading wire:target="runNow('{{ $job->id }}')" class="flex items-center gap-2">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Führt aus...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
</div>
