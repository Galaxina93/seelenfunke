<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($tasks as $t)
            @php
                $task = is_array($t) ? $t : (array)$t;
                $title = $task['title'] ?? $task['name'] ?? 'Unnamed Task';
                $status = $task['status'] ?? 'pending';
                $desc = $task['description'] ?? null;
                $dueDate = $task['due_date'] ?? $task['deadline'] ?? null;
            @endphp
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 hover:border-[color:var(--theme-color-40)] transition-colors group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-[color:var(--theme-color)] opacity-5 rounded-full blur-2xl group-hover:opacity-10 transition-all duration-500 pointer-events-none"></div>
                
                <div class="flex items-start justify-between mb-3 relative z-10">
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[9px] font-black uppercase tracking-widest
                        {{ $status === 'completed' || $status === 'fertig' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 
                           ($status === 'in_progress' || $status === 'in arbeit' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 
                           'bg-gray-800 text-gray-400 border border-gray-700') }}">
                        {{ $status }}
                    </span>
                    @if($dueDate)
                        <span class="text-xs text-[color:var(--theme-color-50)] font-mono">
                            <i class="bi bi-clock mr-1"></i>{{ $dueDate }}
                        </span>
                    @endif
                </div>

                <h4 class="text-white font-bold text-base leading-tight mb-2 relative z-10">{{ $title }}</h4>
                
                @if($desc)
                    <p class="text-xs text-gray-400 line-clamp-2 leading-relaxed relative z-10">{{ $desc }}</p>
                @endif
                
                <!-- raw dump -->
                <div class="mt-4 border-t border-gray-800/80 pt-3 relative z-10">
                    <details class="text-[10px] text-gray-500 group/details">
                        <summary class="cursor-pointer font-bold uppercase tracking-widest hover:text-[color:var(--theme-color)] transition-colors list-none flex items-center gap-1">
                            <i class="bi bi-code-slash"></i> Payload ansehen
                        </summary>
                        <div class="mt-2 bg-black/40 p-2 rounded-lg font-mono text-[9px] overflow-x-auto custom-scrollbar">
                            @foreach($task as $k => $v)
                                @if(!in_array($k, ['title', 'name', 'status', 'description', 'due_date', 'deadline']))
                                    <div class="text-[color:var(--theme-color-70)]"><strong class="text-gray-500">{{ $k }}:</strong> {{ is_array($v) ? json_encode($v) : $v }}</div>
                                @endif
                            @endforeach
                        </div>
                    </details>
                </div>
            </div>
        @endforeach
    </div>
</div>
