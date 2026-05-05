                    <!-- Workspace View -->
                    <div wire:key="tab-workspace" x-show="activeTab === 'workspace'" 
                         x-ref="workspaceContainer"
                         class="flex-1 flex flex-col gap-2 overflow-hidden h-full w-full relative">
                         
                    <!-- TOP: Workspace Kanban Canvas -->
                    <div class="flex-1 min-h-0 shrink-0 rounded-2xl border border-gray-800 relative overflow-hidden shadow-[inset_0_0_50px_rgba(0,0,0,1)] bg-[#050505]" :class="showWorkspaceMobile ? 'flex' : 'hidden lg:flex'" style="background-image: linear-gradient(var(--theme-color-5) 1px, transparent 1px), linear-gradient(90deg, var(--theme-color-5) 1px, transparent 1px); background-size: 3rem 3rem;">
                    <div class="absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-emerald-500/5 to-transparent pointer-events-none"></div>
                
                <!-- Heartbeat Monitor (Ultra-Realistic HTML5 Canvas) -->
                <!-- Heartbeat Monitor (Ultra-Realistic HTML5 Canvas) -->
                <div wire:ignore class="absolute top-0 inset-x-0 h-16 pointer-events-none z-10 overflow-hidden" 
                     x-data="ekgMonitorComponent()" 
                     x-init="initCanvas()">
                    <canvas x-ref="canvas" class="w-full h-full opacity-60"></canvas>
                </div>

                <script>
                    function ekgMonitorComponent() {
                        return {
                            initCanvas() {
                                const canvas = this.$refs.canvas;
                                const ctx = canvas.getContext('2d');
                                
                                let width, height;
                                
                                const resize = () => {
                                    const rect = canvas.getBoundingClientRect();
                                    const dpr = window.devicePixelRatio || 1;
                                    canvas.width = rect.width * dpr;
                                    canvas.height = rect.height * dpr;
                                    width = canvas.width;
                                    height = canvas.height;
                                    ctx.scale(dpr, dpr);
                                    width = rect.width;
                                    height = rect.height;
                                };

                                window.addEventListener('resize', resize);
                                resize();

                                const points = [];
                                let currentX = 0;
                                const speed = 0.8; 
                                const MAX_AGE = 300; 
                                let frameCount = 0;
                                let isAgentActive = false;
                                let isWorkerRunning = true;

                                const animate = () => {
                                    requestAnimationFrame(animate);
                                    frameCount++;
                                    
                                    ctx.clearRect(0, 0, width, height);

                                    if (frameCount % 30 === 0) {
                                        const workerNode = document.getElementById('worker-status-node');
                                        isWorkerRunning = workerNode ? workerNode.getAttribute('data-running') === 'true' : true;
                                        const activeTask = document.querySelector('span.bg-\\[var\\(--theme-color-10\\)\\]');
                                        const typingAgent = document.querySelector('.animate-bounce');
                                        
                                        // The heart only beats if background queue worker is strictly running AND doing work (or typing).
                                        isAgentActive = !!(activeTask || typingAgent) && isWorkerRunning;
                                        
                                        // Bonus: If the worker is DEAD, turn the line into a flatline RED immediately
                                        if (!isWorkerRunning) {
                                            isAgentActive = false;
                                        }
                                    }

                                    currentX += 1.5 * speed;
                                    if (currentX >= width) {
                                        currentX = 0;
                                        points.length = 0; 
                                    }

                                    let y = height / 2;
                                    let localX = currentX % 250; 
                                    
                                    if (isAgentActive && localX > 100 && localX < 150) {
                                        if (localX < 110) { 
                                            y -= Math.sin((localX - 100) * Math.PI / 10) * 3; 
                                        } else if (localX < 115) { 
                                            y += (localX - 110) * 1.5; 
                                        } else if (localX < 120) { 
                                            y += 7.5 - (localX - 115) * 8; 
                                        } else if (localX < 125) { 
                                            y += -32.5 + (localX - 120) * 9; 
                                        } else if (localX < 130) { 
                                            y += 12.5 - (localX - 125) * 2.5; 
                                        } else if (localX < 145) { 
                                            y -= Math.sin((localX - 130) * Math.PI / 15) * 4; 
                                        }
                                    } else {
                                        y += (Math.sin(currentX * 0.05) * 0.5);
                                    }

                                    y += (Math.random() - 0.5) * 0.3;

                                    points.push({ x: currentX, y: y, age: 0 });

                                    if (points.length > 1) {
                                        for (let i = 1; i < points.length; i++) {
                                            const p1 = points[i - 1];
                                            const p2 = points[i];
                                            
                                            p1.age += 1;
                                            if (p1.age > MAX_AGE) continue;
                                            
                                            const alpha = Math.max(0, 1 - (p1.age / MAX_AGE));
                                            
                                            ctx.beginPath();
                                            ctx.moveTo(p1.x, p1.y);
                                            ctx.lineTo(p2.x, p2.y);
                                            
                                            if (!isWorkerRunning) {
                                                ctx.strokeStyle = `rgba(239, 68, 68, ${alpha * 0.8})`; // Red Flatline
                                            } else if (isAgentActive) {
                                                ctx.strokeStyle = `rgba(16, 185, 129, ${alpha})`;
                                            } else {
                                                ctx.strokeStyle = `rgba(16, 185, 129, ${alpha * 0.4})`;
                                            }
                                            
                                            ctx.lineWidth = 1.5;
                                            ctx.lineJoin = 'round';
                                            ctx.lineCap = 'round';
                                            ctx.stroke();
                                        }
                                    }

                                    while (points.length > 0 && points[0].age > MAX_AGE) {
                                        points.shift();
                                    }

                                    ctx.beginPath();
                                    ctx.arc(currentX, y, 4, 0, Math.PI * 2);
                                    const gradient = ctx.createRadialGradient(currentX, y, 0, currentX, y, 8);
                                    
                                    if (!isWorkerRunning) {
                                        gradient.addColorStop(0, 'rgba(239, 68, 68, 0.8)'); 
                                        gradient.addColorStop(1, 'rgba(220, 38, 38, 0)');
                                    } else if (isAgentActive) {
                                        gradient.addColorStop(0, 'rgba(52, 211, 153, 0.9)'); 
                                        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                                    } else {
                                        gradient.addColorStop(0, 'rgba(52, 211, 153, 0.3)'); 
                                        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                                    }
                                    ctx.fillStyle = gradient;
                                    ctx.fill();
                                    
                                    ctx.beginPath();
                                    ctx.arc(currentX, y, 1.5, 0, Math.PI * 2);
                                    if (!isWorkerRunning) {
                                        ctx.fillStyle = '#fca5a5';
                                    } else {
                                        ctx.fillStyle = isAgentActive ? '#a7f3d0' : '#4ade80';
                                    }
                                    ctx.fill();
                                };
                                
                                setTimeout(animate, 100);
                            }
                        }
                    }
                </script>

                <!-- Background Processing Info Icon -->
                <div class="absolute top-3 right-4 z-20" x-data="{ showInfo: false }" @click.away="showInfo = false">
                    <button type="button" @click.prevent="showInfo = !showInfo" class="w-6 h-6 rounded-full bg-gray-900 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-emerald-400 hover:border-emerald-500/50 cursor-pointer transition-all shadow-xl shadow-black relative z-30">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                    </button>
                    <!-- Tooltip -->
                    <div x-show="showInfo" x-transition style="display: none;" class="absolute top-full right-0 mt-2 w-72 bg-gray-950 border border-emerald-500/30 text-gray-300 text-[10px] p-4 rounded-xl shadow-2xl shadow-emerald-500/10 font-sans leading-relaxed pointer-events-auto z-40">
                        <strong class="{{ $this->isWorkerRunning ? 'text-emerald-400' : 'text-red-500' }} block mb-1 uppercase tracking-widest text-[9px] cursor-help pointer-events-auto">
                            {{ $this->isWorkerRunning ? 'Hintergrund-Herzschlag' : 'WARNUNG: WORKER OFFLINE' }}
                        </strong>
                        Sobald ein Agent einer Aufgabe zugewiesen ist, arbeitet er komplett autark im Hintergrundfenster.<br><br>
                        Der EKG Monitor zieht eine flache Linie, bis Hintergrundaktiviät (Tasks oder Chat) wahrgenommen wird.<br><br>
                        Das System schlägt nur aus, wenn der Worker reell läuft.
                        Damit dies lokal aktiv ist, muss der Queue Worker laufen:<br>
                        <code class="block bg-gray-900 text-emerald-300 p-1.5 mt-1.5 rounded border border-gray-800 font-mono text-[9px] break-all">php artisan queue:work</code>
                        <div class="mt-3 bg-gray-900 p-2 rounded text-gray-400 text-[8px] font-mono border border-gray-800 shadow-inner">
                            <span class="text-[var(--theme-color)] font-bold">Diagnose-Pings:</span><br>
                            {{ $this->workerDiagnostic }}
                        </div>
                    </div>
                </div>

                <div class="w-full h-full pt-20 pb-4 px-4 lg:pt-24 lg:pb-8 lg:px-8 overflow-y-auto lg:overflow-hidden lg:flex lg:flex-col" id="war-room-canvas">
                    <!-- Hidden attribute exposing worker state to JS (Must be inside polled DOM) -->
                    <div id="worker-status-node" class="hidden" data-running="{{ $this->isWorkerRunning ? 'true' : 'false' }}"></div>

                    <!-- ============================== -->
                    <!-- MOBILE: KACHEL-ANSICHT         -->
                    <!-- ============================== -->
                    <div class="flex lg:hidden w-full flex-wrap gap-4 items-start content-start">
@foreach($tasks as $task)
                        <div class="task-node relative w-full lg:w-80 {{ $task->parent_task_id ? 'ml-0 lg:ml-8 border-l-4 border-l-[var(--theme-color-50)]' : '' }} bg-gray-950/90 backdrop-blur-md border {{ $task->status === 'completed' ? 'border-[var(--theme-color-50)] shadow-xl shadow-[var(--theme-color-10)]' : ($task->status === 'processing' ? 'border-[var(--theme-color-50)] shadow-[0_0_15px_var(--theme-color-10)] animate-pulse-slow' : 'border-gray-800') }} rounded-2xl p-5 flex flex-col transition-all shrink-0"
                             @if($task->status === 'pending')
                                 x-on:dragover.prevent="dragOver($event)"
                                 x-on:dragleave.prevent="dragLeave($event)"
                                 x-on:drop.prevent="dropTask($event, '{{ $task->id }}')"
                             @endif
                             id="task-{{ $task->id }}">
                            
                            @if($task->parent_task_id)
                                <div class="absolute -left-10 top-8 text-[var(--theme-color-30)] hidden lg:block">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                </div>
                            @endif
                            
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">#{{ substr($task->id, 0, 8) }}</span>
                                <div class="flex items-center gap-2">
                                    @if($task->status === 'completed')
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] uppercase tracking-widest">Fertig</span>
                                        <button wire:click="undoTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-[var(--theme-color)] p-1 rounded-md hover:bg-[var(--theme-color-10)] transition-colors"
                                                title="Aufgabe rückgängig machen (Umkehren)">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                                        </button>
                                        <button wire:click="restartTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-emerald-400 p-1 rounded-md hover:bg-emerald-500/10 transition-colors"
                                                title="Aufgabe komplett neu starten">
                                            <x-heroicon-o-arrow-path class="w-4 h-4" />
                                        </button>
                                        <button wire:click="archiveTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-orange-400 p-1 rounded-md hover:bg-orange-500/10 transition-colors"
                                                title="Archivieren & Ausblenden">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v2.25c0 1.08-.896 1.95-2 1.95H5.75c-1.104 0-2-.87-2-1.95v-2.25M12 21.75V11.25m-3 3.75 3-3.75 3 3.75M9 7.5h6" />
                                            </svg>
                                        </button>
                                        <button wire:click="deleteTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-red-500 p-1 rounded-md hover:bg-red-500/10 transition-colors"
                                                wire:confirm="Aufgabe unwiderruflich löschen?"
                                                title="Aufgabe unwiderruflich löschen">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    @elseif($task->status === 'processing')
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] uppercase tracking-widest flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)] animate-pulse-slow"></span> Läuft
                                            </span>
                                            <button type="button" wire:click="cancelTask('{{ $task->id }}')" 
                                                    class="text-gray-500 hover:text-red-400 p-0.5 rounded-md hover:bg-red-500/10 transition-colors"
                                                    title="Task stoppen (Abbruch)">
                                                <x-heroicon-s-x-mark class="w-4 h-4" />
                                            </button>
                                        </div>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold {{ $task->status === 'failed' ? 'bg-red-500/10 text-red-400 border-red-500/30' : 'bg-gray-800 text-gray-400 border-gray-700' }} border uppercase tracking-widest">
                                            {{ $task->status === 'failed' ? 'Fehlgeschlagen' : 'Wartet' }}
                                        </span>
                                        @if($task->status === 'failed' || $task->status === 'completed')
                                            <button wire:click="restartTask('{{ $task->id }}')" 
                                                    class="text-gray-500 hover:text-emerald-400 p-1 rounded-md hover:bg-emerald-500/10 transition-colors"
                                                    title="Aufgabe komplett neu starten">
                                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                                            </button>
                                            <button x-data @click="let a = prompt('Gibt es Ergänzungen? Aufgabe wird danach neu gestartet.'); if(a) { $wire.appendAndRestartTask('{{ $task->id }}', a) }"
                                                    class="text-gray-500 hover:text-emerald-400 p-1 rounded-md hover:bg-emerald-500/10 transition-colors"
                                                    title="Aufgabe ergänzen & neu starten">
                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                            </button>
                                        @endif
                                        <button wire:click="deleteTask('{{ $task->id }}')" 
                                                class="text-gray-500 hover:text-red-500 p-1 rounded-md hover:bg-red-500/10 transition-colors"
                                                wire:confirm="Aufgabe unwiderruflich löschen?"
                                                title="Aufgabe unwiderruflich löschen">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            </div>
                            
                            <p class="text-sm text-gray-300 leading-relaxed font-sans mb-4">{{ $task->prompt }}</p>

                            @if(!empty($task->ui_metadata['attachments']) || !empty($task->ui_metadata['local_uploads']))
                                <div class="flex flex-wrap gap-2 mb-4 -mt-2">
                                    @foreach($task->ui_metadata['attachments'] ?? [] as $att)
                                        <div class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 rounded-md text-[10px] font-mono flex items-center gap-1.5 shadow-sm">
                                            <x-heroicon-o-document-text class="w-3 h-3" />
                                            {{ basename($att) }}
                                        </div>
                                    @endforeach
                                    @foreach($task->ui_metadata['local_uploads'] ?? [] as $upl)
                                        <div class="px-2 py-1 bg-purple-500/10 text-purple-400 border border-purple-500/30 rounded-md text-[10px] font-mono flex items-center gap-1.5 shadow-sm">
                                            <x-heroicon-o-paper-clip class="w-3 h-3" />
                                            {{ $upl['name'] ?? 'Upload' }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($task->status === 'processing' && $task->assigned_agent_id)
                                @php
                                    $agentState = \Illuminate\Support\Facades\Cache::get('ai_live_state_' . $task->assigned_agent_id);
                                    $colorMap = [
                                        'indigo' => ['bg' => 'rgba(99, 102, 241, 0.1)', 'border' => 'rgba(99, 102, 241, 0.3)', 'text' => '#818cf8'],
                                        'yellow' => ['bg' => 'rgba(234, 179, 8, 0.1)', 'border' => 'rgba(234, 179, 8, 0.3)', 'text' => '#facc15'],
                                        'orange' => ['bg' => 'rgba(249, 115, 22, 0.1)', 'border' => 'rgba(249, 115, 22, 0.3)', 'text' => '#fb923c'],
                                        'emerald' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'border' => 'rgba(16, 185, 129, 0.3)', 'text' => '#34d399'],
                                        'cyan' => ['bg' => 'rgba(6, 182, 212, 0.1)', 'border' => 'rgba(6, 182, 212, 0.3)', 'text' => '#22d3ee'],
                                        'green' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'border' => 'rgba(34, 197, 94, 0.3)', 'text' => '#4ade80'],
                                    ];
                                @endphp
                                @if($agentState)
                                    @php
                                        $pcs = $colorMap[$agentState['pulse_color']] ?? $colorMap['indigo'];
                                    @endphp
                                    <div class="mb-4 p-2.5 rounded-xl flex items-center gap-3 relative overflow-hidden transition-all shadow-inner" 
                                         style="background-color: {{ $pcs['bg'] }}; border: 1px solid {{ $pcs['border'] }};">
                                        
                                        <div class="w-7 h-7 rounded-full flex justify-center items-center shrink-0 border"
                                             style="border-color: {{ $pcs['border'] }}; color: {{ $pcs['text'] }}; background-color: rgba(0,0,0,0.2);">
                                            <x-dynamic-component :component="'heroicon-o-' . $agentState['active_node']" 
                                                                 class="w-4 h-4 animate-pulse-slow" />
                                        </div>

                                        <div class="flex-1 min-w-0 pr-1">
                                            <span class="block text-[8px] uppercase tracking-widest font-bold mb-0.5" style="color: {{ $pcs['text'] }}; opacity: 0.6;">
                                                Sub-Prozess
                                            </span>
                                            <span class="block text-xs font-mono truncate" style="color: {{ $pcs['text'] }};" title="{{ $agentState['action_text'] }}">
                                                {{ $agentState['action_text'] }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            <div class="mt-auto pt-4 border-t border-gray-800/80 lg:pointer-events-none">
                                @if($task->assigned_agent_id && $task->agent)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-gray-900 border border-gray-700 overflow-hidden shrink-0">
                                            @if($task->agent->profile_picture)
                                                <img src="{{ \Illuminate\Support\Str::startsWith($task->agent->profile_picture, 'shop/') ? asset($task->agent->profile_picture) : Storage::url($task->agent->profile_picture) }}" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <span class="text-xs text-gray-400 font-bold truncate">{{ $task->agent->name }}</span>
                                        @if($task->agent->provider === 'openai')
                                            <span class="ml-auto text-[9px] uppercase tracking-wider text-green-400 font-bold bg-green-500/10 border border-green-500/20 px-1.5 py-0.5 rounded shadow-inner" title="OpenAI GPT">GPT</span>
                                        @elseif($task->agent->provider === 'anthropic')
                                            <span class="ml-auto text-[9px] uppercase tracking-wider text-orange-400 font-bold bg-orange-500/10 border border-orange-500/20 px-1.5 py-0.5 rounded shadow-inner" title="Anthropic Claude">CLAUDE</span>
                                        @else
                                            <span class="ml-auto text-[9px] uppercase tracking-wider text-blue-400 font-bold bg-blue-500/10 border border-blue-500/20 px-1.5 py-0.5 rounded shadow-inner" title="Google Gemini">GEMINI</span>
                                        @endif
                                    </div>
                                @elseif($task->status === 'pending')
                                    <div class="hidden lg:flex h-8 border border-dashed border-gray-700 rounded-lg items-center justify-center text-xs text-gray-500 bg-gray-950/20 task-dropzone transition-colors drop-target-highlight pointer-events-none">
                                        Agent hier ablegen
                                    </div>
                                    <div class="lg:hidden">
                                         <select class="w-full h-9 bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-lg text-xs text-[var(--theme-color)] focus:ring-emerald-500 focus:border-[var(--theme-color)]"
                                                 wire:change="assignAgent('{{ $task->id }}', $event.target.value)">
                                             <option value="">Agent zuweisen...</option>
                                             @foreach($agents as $agent)
                                                 <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                             @endforeach
                                         </select>
                                    </div>
                                @endif
                            </div>

                            <!-- DETAILS (Plan, Log, Response) -->
                            @include('livewire.shop.ai.blocks.task-details', ['task' => $task, 'idPrefix' => 'mob', 'marginClass' => 'mt-4'])
                        </div>
                    @endforeach
                    </div>

                    <!-- ============================== -->
                    <!-- DESKTOP: TABELLEN-ANSICHT      -->
                    <!-- ============================== -->
                    <div class="hidden lg:flex lg:flex-col lg:flex-1 min-h-0 w-full text-white">
                        @if($tasks->isNotEmpty())
                            <div class="overflow-x-auto overflow-y-auto flex-1 rounded-xl border border-gray-800 bg-gray-950/50 backdrop-blur-md shadow-2xl custom-scrollbar">
                                <table class="w-full text-left border-collapse relative">
                                    <thead class="sticky top-0 z-20 bg-gray-900 shadow-sm border-b border-gray-800">
                                        <tr class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">
                                            <th class="px-6 py-4">ID & Aufgabe</th>
                                            <th class="px-6 py-4 w-48">Agent</th>
                                            <th class="px-6 py-4 w-56">Status</th>
                                            <th class="px-6 py-4 w-32 text-right">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-800/60">
                                        @foreach($tasks as $task)
                                        <tr class="transition-colors hover:bg-gray-900/40 {{ $task->parent_task_id ? 'bg-gray-900/20' : '' }}"
                                            @if($task->status === 'pending')
                                                x-on:dragover.prevent="dragOver($event)"
                                                x-on:dragleave.prevent="dragLeave($event)"
                                                x-on:drop.prevent="dropTask($event, '{{ $task->id }}')"
                                            @endif
                                            id="task-desktop-{{ $task->id }}">
                                            
                                            <!-- ID & Aufgabe (+ Anhänge) -->
                                            <td class="px-6 py-5 align-top w-1/2">
                                                <div class="flex items-center gap-2 mb-2">
                                                    @if($task->parent_task_id)
                                                        <svg class="w-4 h-4 text-[var(--theme-color-50)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                                    @endif
                                                    <span class="text-[10px] font-mono text-[var(--theme-color-50)] uppercase tracking-widest">#{{ substr($task->id, 0, 8) }}</span>
                                                    @if(!empty($task->ui_metadata['attachments']) || !empty($task->ui_metadata['local_uploads']))
                                                        <div class="flex flex-wrap gap-1.5 ml-2">
                                                        @foreach($task->ui_metadata['attachments'] ?? [] as $att)
                                                            <span class="px-1.5 py-0.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 rounded text-[9px] font-mono items-center inline-flex gap-1 shadow-sm"><x-heroicon-o-document-text class="w-3 h-3" /> {{ basename($att) }}</span>
                                                        @endforeach
                                                        @foreach($task->ui_metadata['local_uploads'] ?? [] as $upl)
                                                            <span class="px-1.5 py-0.5 bg-purple-500/10 text-purple-400 border border-purple-500/30 rounded text-[9px] font-mono items-center inline-flex gap-1 shadow-sm"><x-heroicon-o-paper-clip class="w-3 h-3" /> {{ $upl['name'] ?? 'Upload' }}</span>
                                                        @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-gray-200 leading-relaxed font-sans mb-3">{{ $task->prompt }}</p>

                                                <!-- DETAILS (Plan, Log, Response) -->
                                                @include('livewire.shop.ai.blocks.task-details', ['task' => $task, 'idPrefix' => 'desk', 'marginClass' => 'mt-2'])
                                            </td>

                                            <!-- Agent -->
                                            <td class="px-6 py-5 align-top">
                                                @if($task->assigned_agent_id && $task->agent)
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-10 h-10 rounded-full bg-gray-900 border border-gray-700 overflow-hidden shrink-0 shadow-md">
                                                            @if($task->agent->profile_picture)
                                                                <img src="{{ \Illuminate\Support\Str::startsWith($task->agent->profile_picture, 'shop/') ? asset($task->agent->profile_picture) : Storage::url($task->agent->profile_picture) }}" class="w-full h-full object-cover">
                                                            @endif
                                                        </div>
                                                        <div class="flex flex-col gap-0.5">
                                                             <span class="block text-xs text-gray-300 font-bold truncate">{{ $task->agent->name }}</span>
                                                             @if($task->agent->provider === 'openai')
                                                                 <span class="text-[9px] uppercase tracking-wider text-green-400 font-bold w-max">GPT</span>
                                                             @elseif($task->agent->provider === 'anthropic')
                                                                 <span class="text-[9px] uppercase tracking-wider text-orange-400 font-bold w-max">CLAUDE</span>
                                                             @else
                                                                 <span class="text-[9px] uppercase tracking-wider text-blue-400 font-bold w-max">GEMINI</span>
                                                             @endif
                                                        </div>
                                                    </div>
                                                @elseif($task->status === 'pending')
                                                    <div class="h-10 px-3 border border-dashed border-gray-700 rounded-lg items-center justify-center text-xs text-gray-500 bg-gray-950/20 task-dropzone transition-colors drop-target-highlight pointer-events-none flex">
                                                        Agent ablegen
                                                    </div>
                                                @endif
                                            </td>

                                            <!-- Status -->
                                            <td class="px-6 py-5 align-top">
                                                @if($task->status === 'completed')
                                                    <span class="px-2.5 py-1 rounded text-[10px] font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] uppercase tracking-widest shadow-sm">Fertig</span>
                                        @elseif($task->status === 'awaiting_approval')
                                            <span class="px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/30 uppercase tracking-widest shadow-sm flex items-center w-max gap-1">
                                                <x-heroicon-o-hand-raised class="w-3 h-3" /> Warten auf Freigabe
                                            </span>
                                                @elseif($task->status === 'processing')
                                                    <div class="flex flex-col gap-2.5">
                                                        <span class="inline-flex w-fit px-2.5 py-1 rounded text-[10px] font-bold bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] uppercase tracking-widest items-center gap-1.5 shadow-[0_0_10px_var(--theme-color-10)]">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)] animate-pulse-slow"></span> Läuft
                                                        </span>
                                                        
                                                        @if($task->assigned_agent_id)
                                                            @php
                                                                $agentState = \Illuminate\Support\Facades\Cache::get('ai_live_state_' . $task->assigned_agent_id);
                                                                $colorMap = [
                                                                      'indigo' => ['bg' => 'rgba(99, 102, 241, 0.1)', 'border' => 'rgba(99, 102, 241, 0.3)', 'text' => '#818cf8'],
                                                                      'yellow' => ['bg' => 'rgba(234, 179, 8, 0.1)', 'border' => 'rgba(234, 179, 8, 0.3)', 'text' => '#facc15'],
                                                                      'orange' => ['bg' => 'rgba(249, 115, 22, 0.1)', 'border' => 'rgba(249, 115, 22, 0.3)', 'text' => '#fb923c'],
                                                                      'emerald' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'border' => 'rgba(16, 185, 129, 0.3)', 'text' => '#34d399'],
                                                                      'cyan' => ['bg' => 'rgba(6, 182, 212, 0.1)', 'border' => 'rgba(6, 182, 212, 0.3)', 'text' => '#22d3ee'],
                                                                      'green' => ['bg' => 'rgba(34, 197, 94, 0.1)', 'border' => 'rgba(34, 197, 94, 0.3)', 'text' => '#4ade80'],
                                                                ];
                                                            @endphp
                                                            @if($agentState)
                                                                @php $pcs = $colorMap[$agentState['pulse_color']] ?? $colorMap['indigo']; @endphp
                                                                <div class="p-2 rounded-lg flex items-center gap-2 border bg-opacity-50" style="background-color: {{ $pcs['bg'] }}; border-color: {{ $pcs['border'] }};">
                                                                     <x-dynamic-component :component="'heroicon-o-' . $agentState['active_node']" class="w-3.5 h-3.5 shrink-0 animate-pulse-slow" style="color: {{ $pcs['text'] }};" />
                                                                     <span class="text-[10px] font-mono truncate max-w-[160px]" style="color: {{ $pcs['text'] }};" title="{{ $agentState['action_text'] }}">{{ $agentState['action_text'] }}</span>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="px-2.5 py-1 inline-flex w-fit rounded text-[10px] font-bold border uppercase tracking-widest shadow-sm {{ $task->status === 'failed' ? 'bg-red-500/10 text-red-400 border-red-500/30' : 'bg-gray-800 text-gray-400 border-gray-700' }}">
                                                        {{ $task->status === 'failed' ? 'Fehlgeschlagen' : 'Wartet' }}
                                                    </span>
                                                @endif
                                            </td>

                                            <!-- Aktionen -->
                                            <td class="px-6 py-5 align-top text-right">
                                                <div class="flex justify-end items-center gap-2">
                                                    @if($task->status === 'processing')
                                                        <button type="button" wire:click="cancelTask('{{ $task->id }}')" class="text-gray-500 hover:text-red-400 p-2 rounded-lg hover:bg-red-500/10 transition-colors shadow-sm" title="Task stoppen">
                                                            <x-heroicon-s-x-mark class="w-4 h-4" />
                                                        </button>
                                                    @else
                                                        @if($task->status === 'completed' || $task->status === 'failed')
                                                            <button wire:click="restartTask('{{ $task->id }}')" class="text-gray-500 hover:text-emerald-400 p-2 rounded-lg hover:bg-emerald-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Neu starten">
                                                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                                                            </button>
                                                            <button x-data @click="let a = prompt('Was möchtest du als Ergänzung/Fehlermeldung hinzufügen?'); if(a) { $wire.appendAndRestartTask('{{ $task->id }}', a) }" class="text-gray-500 hover:text-emerald-400 p-2 rounded-lg hover:bg-emerald-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Ergänzen & Neu starten">
                                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                            </button>
                                                        @endif
                                                        @if($task->status === 'completed')
                                                            <button wire:click="undoTask('{{ $task->id }}')" class="text-gray-500 hover:text-[var(--theme-color)] p-2 rounded-lg hover:bg-[var(--theme-color-10)] transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Aufgabe rückgängig machen (Umkehren)">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                                                            </button>
                                                            <button wire:click="archiveTask('{{ $task->id }}')" class="text-gray-500 hover:text-orange-400 p-2 rounded-lg hover:bg-orange-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" title="Archivieren & Ausblenden">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v2.25c0 1.08-.896 1.95-2 1.95H5.75c-1.104 0-2-.87-2-1.95v-2.25M12 21.75V11.25m-3 3.75 3-3.75 3 3.75M9 7.5h6" /></svg>
                                                            </button>
                                                        @endif
                                                        <button wire:click="deleteTask('{{ $task->id }}')" class="text-gray-500 hover:text-red-500 p-2 rounded-lg hover:bg-red-500/10 transition-colors shadow-sm bg-gray-900 border border-gray-800/60" wire:confirm="Aufgabe unwiderruflich löschen?" title="Aufgabe löschen">
                                                            <x-heroicon-o-trash class="w-4 h-4" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if($tasks->isEmpty())
                        <div class="w-full h-[60vh] flex flex-col items-center justify-center text-gray-600 opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5zm0 9.75h2.25A2.25 2.25 0 0010.5 18v-2.25a2.25 2.25 0 00-2.25-2.25H6a2.25 2.25 0 00-2.25 2.25V18A2.25 2.25 0 006 20.25zm9.75-9.75H18a2.25 2.25 0 002.25-2.25V6A2.25 2.25 0 0018 3.75h-2.25A2.25 2.25 0 0013.5 6v2.25a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <p class="uppercase tracking-widest text-sm font-bold">Die Arbeitsfläche ist leer</p>
                            <p class="text-xs mt-2 font-sans">Schreibe mit der KI um Tasks generieren zu lassen.</p>
                        </div>
                    @endif
                    </div>
                </div>
            </div>
