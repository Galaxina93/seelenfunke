<div x-show="showTasks" x-transition x-cloak class="fixed left-6 top-24 z-50 flex flex-col gap-3 pointer-events-auto max-w-[300px] w-full">
    <div class="text-[10px] font-black uppercase tracking-widest text-emerald-500/50 border-b border-emerald-900/30 pb-2 mb-1 bg-black/40 px-2 pt-2 rounded-t-lg backdrop-blur-md">KI Aufgaben (Live)</div>
    @if(isset($tasks) && count($tasks) > 0)
        @foreach($tasks as $task)
            <div class="bg-black/60 backdrop-blur-md border border-gray-800 {{ $task->status === 'processing' ? 'border-cyan-500/50 shadow-[0_0_15px_rgba(6,182,212,0.1)] animate-pulse-slow' : '' }} rounded-xl p-3 flex flex-col transition-all">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-[9px] font-mono text-gray-500 uppercase tracking-widest">#{{ substr($task->id, 0, 8) }}</span>
                    <div class="flex gap-1">
                        @if($task->status === 'processing')
                            <button type="button" wire:click="pauseTask('{{ $task->id }}')" class="px-2 py-0.5 rounded text-[9px] font-bold bg-yellow-500/10 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/20 uppercase tracking-widest transition-colors" title="Aufgabe pausieren">
                                PAUSE
                            </button>
                        @elseif($task->status === 'paused')
                            <button type="button" wire:click="restartTask('{{ $task->id }}')" class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/20 uppercase tracking-widest transition-colors" title="Aufgabe fortsetzen">
                                RESUME
                            </button>
                        @endif
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold {{ $task->status === 'failed' ? 'bg-red-500/10 text-red-400 border-red-500/30' : ($task->status === 'paused' ? 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30' : ($task->status === 'awaiting_approval' ? 'bg-amber-500/10 text-amber-500 border-amber-500/30' : 'bg-gray-800 text-gray-400 border-gray-700')) }} border uppercase tracking-widest">
                            {{ $task->status === 'awaiting_approval' ? 'Wartet auf Freigabe' : $task->status }}
                        </span>
                    </div>
                </div>
                <p class="text-[11px] text-gray-300 leading-relaxed font-sans line-clamp-2" title="{{ $task->prompt }}">{{ $task->prompt }}</p>
                
                <!-- DETAILS (Plan, Log, Response) -->
                @include('livewire.shop.ai.blocks.task-details', ['task' => $task, 'idPrefix' => 'wdg', 'marginClass' => 'mt-2'])

                @if($task->assigned_agent_id && $task->agent)
                    <div class="mt-2 flex items-center gap-1.5 opacity-80">
                        <img src="{{ \Illuminate\Support\Str::startsWith($task->agent->profile_picture, 'shop/') ? asset($task->agent->profile_picture) : Storage::url($task->agent->profile_picture) }}" class="w-4 h-4 rounded-full object-cover">
                        <span class="text-[9px] text-gray-400 font-bold truncate">{{ $task->agent->name }}</span>
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="bg-black/60 backdrop-blur-md border border-gray-800 rounded-xl p-3 flex flex-col text-center">
            <span class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Keine aktiven Aufgaben</span>
        </div>
    @endif
</div>
