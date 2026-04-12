<div class="h-auto min-h-[calc(100dvh-4rem)] lg:h-[calc(100vh-6rem)] w-full font-mono text-emerald-500 flex flex-col pt-4 overflow-hidden relative">
    
    <!-- Neon Header -->
    <div class="text-center mb-4 lg:mb-6 shrink-0 relative z-10 w-full px-4 lg:px-6">
        <h1 class="text-3xl font-black tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md text-emerald-400">Schaltzentrale</h1>
        <p class="text-emerald-700 text-xs font-bold uppercase tracking-widest mt-1">Multi-Agenten Arbeitsfläche (Gen-UI Kommunikation)</p>
    </div>

    <!-- Main Workspace Container -->
    <div class="flex-1 flex flex-col lg:flex-row gap-4 lg:gap-6 px-4 lg:px-6 pb-4 lg:pb-6 overflow-hidden relative"
         x-data="workspaceCanvas()">
         
        <!-- Left Sidebar: Tools & Agents -->
        <div class="w-full lg:w-72 bg-gray-950 border border-emerald-900/40 rounded-2xl p-4 flex flex-col shrink-0 z-10 shadow-[0_0_30px_rgba(16,185,129,0.05)]">
            <h3 class="text-xs uppercase tracking-widest text-emerald-600 mb-4 border-b border-emerald-900/50 pb-2">Auftrag erstellen</h3>
            <form wire:submit="createTask" class="mb-6">
                <textarea wire:model="newTaskPrompt" 
                          placeholder="Anweisung oder Fragestellung eingeben..."
                          class="w-full bg-black/50 border border-emerald-900/50 rounded-xl p-3 text-sm text-emerald-400 placeholder:text-emerald-900 focus:outline-none focus:border-emerald-500 transition-colors resize-none h-24 shadow-inner"
                          @keydown.enter.prevent="$wire.createTask()"></textarea>
                <button type="submit" class="mt-2 w-full py-2 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-emerald-500 hover:text-black transition-all">
                    In den Raum werfen +
                </button>
            </form>

            <h3 class="text-xs uppercase tracking-widest text-emerald-600 mb-2 lg:mb-4 border-b border-emerald-900/50 pb-2">Bereite Agenten <span class="hidden lg:inline">(Ziehbar)</span></h3>
            <div class="lg:flex-1 flex lg:flex-col overflow-x-auto lg:overflow-x-hidden lg:overflow-y-auto space-x-3 lg:space-x-0 lg:space-y-3 custom-scrollbar pb-2 lg:pb-0 pr-0 lg:pr-2">
                @foreach($agents as $agent)
                    <div class="agent-draggable w-auto min-w-[150px] lg:min-w-0 bg-black/50 border border-gray-800 rounded-xl p-3 flex items-center gap-3 cursor-grab lg:hover:border-emerald-500/50 transition-colors group shrink-0 lg:shrink"
                         draggable="true"
                         x-on:dragstart="startDrag($event, '{{ $agent->id }}')">
                        <div class="w-10 h-10 rounded-full bg-gray-900 border border-gray-700 overflow-hidden shrink-0 flex items-center justify-center">
                            @if($agent->profile_picture)
                                <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-xs text-gray-500 group-hover:text-emerald-500 font-bold">{{ substr($agent->name, 0, 2) }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 pointer-events-none">
                            <h4 class="text-sm font-bold text-gray-300 truncate group-hover:text-emerald-400">{{ $agent->name }}</h4>
                            <p class="text-[10px] text-gray-600 truncate">{{ $agent->role->name ?? 'Keine Rolle' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- The Canvas (Right) -->
        <div class="flex-1 min-h-[400px] mb-6 lg:mb-0 rounded-2xl border border-emerald-900/30 relative overflow-hidden shadow-[inset_0_0_50px_rgba(0,0,0,1)] flex bg-[#050505]" style="background-image: linear-gradient(rgba(16, 185, 129, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(16, 185, 129, 0.05) 1px, transparent 1px); background-size: 3rem 3rem;">
            <!-- Canvas Ambient Light -->
            <div class="absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-emerald-500/5 to-transparent pointer-events-none"></div>

            <!-- Task Board -->
            <div class="w-full h-full p-4 lg:p-8 overflow-auto flex flex-wrap gap-4 lg:gap-6 items-start content-start" id="war-room-canvas" wire:poll.2s>
                
                @foreach($tasks as $task)
                    <div class="task-node w-full lg:w-80 bg-gray-950/90 backdrop-blur-md border {{ $task->status === 'completed' ? 'border-emerald-500/50 shadow-[0_0_15px_rgba(16,185,129,0.1)]' : ($task->status === 'processing' ? 'border-cyan-500/50 shadow-[0_0_15px_rgba(6,182,212,0.1)] animate-pulse' : 'border-gray-800') }} rounded-2xl p-5 flex flex-col transition-all shrink-0"
                         @if($task->status === 'pending')
                             x-on:dragover.prevent="dragOver($event)"
                             x-on:dragleave.prevent="dragLeave($event)"
                             x-on:drop.prevent="dropTask($event, '{{ $task->id }}')"
                         @endif
                         id="task-{{ $task->id }}">
                        
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">#{{ substr($task->id, 0, 8) }}</span>
                            @if($task->status === 'completed')
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest">Fertig</span>
                            @elseif($task->status === 'processing')
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 uppercase tracking-widest flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span> Läuft
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-gray-800 text-gray-400 border border-gray-700 uppercase tracking-widest">Wartet</span>
                            @endif
                        </div>
                        
                        <p class="text-sm text-gray-300 leading-relaxed font-sans mb-4">{{ $task->prompt }}</p>

                        <!-- Agent Assignment Socket -->
                        <div class="mt-auto pt-4 border-t border-gray-800/80 lg:pointer-events-none">
                            @if($task->assigned_agent_id && $task->agent)
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-gray-900 border border-gray-700 overflow-hidden shrink-0">
                                        @if($task->agent->profile_picture)
                                            <img src="{{ \Illuminate\Support\Str::startsWith($task->agent->profile_picture, 'shop/') ? asset($task->agent->profile_picture) : Storage::url($task->agent->profile_picture) }}" class="w-full h-full object-cover">
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-400 font-bold truncate">{{ $task->agent->name }}</span>
                                </div>
                            @elseif($task->status === 'pending')
                                <!-- Desktop: Dropzone -->
                                <div class="hidden lg:flex h-8 border border-dashed border-gray-700 rounded-lg items-center justify-center text-xs text-gray-500 bg-black/20 task-dropzone transition-colors drop-target-highlight pointer-events-none">
                                    Agent hier ablegen
                                </div>
                                <!-- Mobile: Click Assignment -->
                                <div class="lg:hidden">
                                     <select class="w-full h-9 bg-black/50 border border-emerald-900/50 rounded-lg text-xs text-emerald-500 focus:ring-emerald-500 focus:border-emerald-500"
                                             wire:change="assignAgent('{{ $task->id }}', $event.target.value)">
                                         <option value="">Agent zuweisen...</option>
                                         @foreach($agents as $agent)
                                             <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                         @endforeach
                                     </select>
                                </div>
                            @endif
                        </div>

                        <!-- Response / Gen-UI Area -->
                        @if($task->status === 'completed' && $task->response_content)
                            <div class="mt-4 pt-4 border-t border-emerald-900/30 text-xs text-emerald-300 bg-emerald-950/20 rounded p-3 font-sans break-all">
                                {{ Str::limit($task->response_content, 150) }}
                            </div>
                        @endif
                        
                    </div>
                @endforeach
                
                @if($tasks->isEmpty())
                    <div class="w-full h-64 flex flex-col items-center justify-center text-gray-600 opacity-50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <p class="uppercase tracking-widest text-sm font-bold">Die Arbeitsfläche ist leer</p>
                        <p class="text-xs mt-2 font-sans">Erstelle einen Auftrag und weise ihn durch Ziehen einem Agenten zu.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('workspaceCanvas', () => ({
                draggedAgentId: null,
                
                startDrag(event, agentId) {
                    this.draggedAgentId = agentId;
                    event.dataTransfer.effectAllowed = 'copyMove';
                    event.dataTransfer.setData('text/plain', agentId);
                    setTimeout(() => event.target.classList.add('opacity-30'), 0);
                },
                
                dragOver(event) {
                    let taskNode = event.currentTarget;
                    if(!taskNode.classList.contains('border-emerald-500')) {
                        taskNode.classList.add('border-emerald-500', 'bg-emerald-950/20');
                    }
                },
                
                dragLeave(event) {
                    let taskNode = event.currentTarget;
                    taskNode.classList.remove('border-emerald-500', 'bg-emerald-950/20');
                },
                
                dropTask(event, taskId) {
                    let taskNode = event.currentTarget;
                    taskNode.classList.remove('border-emerald-500', 'bg-emerald-950/20');
                    
                    if(this.draggedAgentId && taskId) {
                        try {
                            @this.assignAgent(taskId, this.draggedAgentId);
                        } catch(e) { console.error('Livewire call failed:', e); }
                    }
                    this.draggedAgentId = null;
                }
            }));
            
            document.addEventListener('dragend', (e) => {
                document.querySelectorAll('.agent-draggable').forEach(el => el.classList.remove('opacity-30'));
            });
        });
    </script>
    
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(16, 185, 129, 0.2); border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.5); }
    </style>
</div>
