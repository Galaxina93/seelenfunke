<!-- DETAILS (Plan, Log, Response) -->
@if((isset($task->ui_metadata['execution_plan']) && count($task->ui_metadata['execution_plan']) > 0) || $task->response_content || isset($task->ui_metadata['work_log']))
    <div wire:key="task-details-{{ $idPrefix ?? 'mob' }}-{{ $task->id }}-{{ $task->status }}" x-data="{ detailsOpen: {{ in_array($task->status, ['completed', 'failed']) ? 'false' : 'true' }} }" class="{{ $marginClass ?? 'mt-4' }}">
        
        @if(in_array($task->status, ['completed', 'failed']))
            <button @click="detailsOpen = !detailsOpen" class="flex items-center gap-1.5 text-[10px] uppercase tracking-widest font-bold text-gray-500 hover:text-[var(--theme-color)] transition-colors mb-2">
                <span x-text="detailsOpen ? 'Details ausblenden' : 'Details & Ausführungsplan einblenden'"></span>
                <x-heroicon-o-chevron-down class="w-3.5 h-3.5 transition-transform" x-bind:class="detailsOpen ? 'rotate-180' : ''" />
            </button>
        @endif

        <div x-show="detailsOpen" x-collapse>
            <!-- EXECUTION PLAN -->
            @if(isset($task->ui_metadata['execution_plan']) && count($task->ui_metadata['execution_plan']) > 0)
                <div class="mb-2 space-y-2 relative z-10 w-full bg-gray-900/40 p-3 rounded-lg border border-gray-800/80">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-[10px] uppercase tracking-widest text-gray-500 font-bold flex items-center gap-2">
                            <x-heroicon-o-clipboard-document-list class="w-3.5 h-3.5"/> KI Ausführungsplan
                        </h4>
                        @if($task->status === 'awaiting_approval')
                            <div class="flex items-center" x-data="{ dropOpen: false }">
                                <button wire:click="approvePlan('{{ $task->id }}')" class="flex items-center gap-1.5 px-3 py-1 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border border-[var(--theme-color-50)] text-[var(--theme-color)] rounded-l-md text-[9px] font-bold uppercase tracking-widest transition-colors">
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> Erlauben
                                </button>
                                <div class="relative">
                                    <button @click="dropOpen = !dropOpen" @click.away="dropOpen = false" class="flex items-center justify-center px-1.5 py-1 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border-y border-r border-[var(--theme-color-50)] text-[var(--theme-color)] rounded-r-md transition-colors">
                                        <x-heroicon-o-chevron-down class="w-3.5 h-3.5" />
                                    </button>
                                    <div x-show="dropOpen" style="display:none;" class="absolute right-0 top-full mt-1 w-40 bg-gray-900 border border-[var(--theme-color-50)] rounded-md shadow-xl overflow-hidden z-30">
                                        <button wire:click="approvePlanAlways('{{ $task->id }}')" class="w-full text-left px-3 py-2 text-[9px] font-bold uppercase tracking-widest text-gray-300 hover:text-white hover:bg-[var(--theme-color-20)] transition-colors">
                                            Immer erlauben
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @foreach($task->ui_metadata['execution_plan'] as $step)
                        <div class="flex items-start gap-2 text-xs font-sans">
                            <div class="shrink-0 mt-0.5">
                                @if($step['status'] === 'pending')
                                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2" stroke-dasharray="2 2" stroke-linecap="round"></circle></svg>
                                @elseif($step['status'] === 'processing')
                                    <svg class="w-4 h-4 text-emerald-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                @elseif($step['status'] === 'completed')
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                @elseif($step['status'] === 'failed')
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="{{ $step['status'] === 'completed' ? 'text-gray-400 line-through' : ($step['status'] === 'processing' ? 'text-[var(--theme-color)]' : 'text-gray-400') }}">
                                    <span class="font-mono text-[9px] opacity-70">SCHRITT {{ $step['id'] }}:</span> {{ $step['description'] ?: '...' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- WORK LOG (Terminal Style) -->
            @if(isset($task->ui_metadata['work_log']) && count($task->ui_metadata['work_log']) > 0)
                <div class="mb-2 relative z-10 w-full bg-black/60 p-2.5 rounded-lg border border-gray-800/80 shadow-inner overflow-hidden flex flex-col max-h-[200px]">
                    <div class="flex items-center gap-2 mb-2 pb-1.5 border-b border-gray-800/50">
                        <div class="w-2 h-2 rounded-full bg-red-500/50"></div>
                        <div class="w-2 h-2 rounded-full bg-yellow-500/50"></div>
                        <div class="w-2 h-2 rounded-full bg-green-500/50"></div>
                        <span class="text-[8px] uppercase tracking-widest text-gray-600 font-bold ml-1">Process Log</span>
                    </div>
                    <div class="overflow-y-auto font-mono text-[9.5px] leading-relaxed flex-1 space-y-1 pr-1 custom-scrollbar" x-data x-init="const observer = new MutationObserver(() => { $el.scrollTop = $el.scrollHeight; }); observer.observe($el, { childList: true, subtree: true }); setTimeout(() => $el.scrollTop = $el.scrollHeight, 100);">
                        @foreach($task->ui_metadata['work_log'] as $log)
                            <div class="flex items-start gap-2">
                                <span class="text-gray-600 shrink-0">[{{ $log['time'] ?? '00:00:00' }}]</span>
                                <span class="{{ isset($log['color']) ? 'text-' . $log['color'] . '-400' : 'text-gray-400' }} {{ ($log['type'] ?? '') === 'info' ? 'animate-pulse' : '' }} break-all">
                                    @if(($log['type'] ?? '') === 'tool')
                                        <svg class="w-2.5 h-2.5 inline-block -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    @elseif(($log['type'] ?? '') === 'success')
                                        <svg class="w-2.5 h-2.5 inline-block -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    @elseif(($log['type'] ?? '') === 'info')
                                        <svg class="w-2.5 h-2.5 inline-block -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    @endif
                                    {!! htmlspecialchars($log['message'] ?? '') !!}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- RESPONSE -->
            @if($task->status === 'completed' && $task->response_content)
                <div class="relative z-20" x-data="{ expandedResp: false }">
                    <div class="text-[12px] text-gray-300 bg-[var(--theme-color-10)] rounded-lg p-3 font-sans break-words bg-opacity-40 relative border border-[var(--theme-color-20)]"
                         :class="expandedResp ? 'max-h-none pb-8' : 'max-h-[70px] overflow-hidden'">
                        @if(strlen($task->response_content) > 80)
                            <div wire:ignore class="ai-markdown-content" x-init="if (window.renderAiMarkdown) { $el.innerHTML = window.renderAiMarkdown(@js($task->response_content)); } else { $el.innerText = @js($task->response_content); }"></div>
                            <div x-show="!expandedResp" class="absolute bottom-0 left-0 right-0 h-10 bg-gradient-to-t from-[var(--theme-color-10)] to-transparent pointer-events-none rounded-b-lg"></div>
                        @else
                            <div class="font-sans whitespace-pre-wrap">{{ $task->response_content }}</div>
                        @endif
                    </div>
                    @if(strlen($task->response_content) > 80)
                        <button @click="expandedResp = !expandedResp" class="w-full text-center text-[10px] uppercase tracking-widest font-bold text-[var(--theme-color)] hover:text-white mt-1 p-1.5 flex justify-center items-center gap-1 transition-colors">
                            <span x-text="expandedResp ? 'Einklappen' : 'Vollständige Antwort lesen'"></span>
                            <x-heroicon-o-chevron-down class="w-3.5 h-3.5 transition-transform" x-bind:class="expandedResp ? 'rotate-180' : ''" />
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
