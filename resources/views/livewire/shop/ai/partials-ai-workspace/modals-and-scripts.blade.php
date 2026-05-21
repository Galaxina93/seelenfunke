    <script>
        (function() {
            const initCanvas = () => {
                if (!window.Alpine) return;
                try {
                    window.Alpine.data('workspaceCanvas', () => ({
                        draggedAgentId: null,
                        startDrag(event, agentId) {
                            this.draggedAgentId = agentId;
                            event.dataTransfer.effectAllowed = 'copyMove';
                            event.dataTransfer.setData('text/plain', agentId);
                            setTimeout(() => event.target.classList.add('opacity-30'), 0);
                        },
                        dragOver(event) {
                            let taskNode = event.currentTarget;
                            if(!taskNode.classList.contains('border-[var(--theme-color)]')) {
                                taskNode.classList.add('border-[var(--theme-color)]', 'bg-[var(--theme-color-10)]');
                            }
                        },
                        dragLeave(event) {
                            let taskNode = event.currentTarget;
                            taskNode.classList.remove('border-[var(--theme-color)]', 'bg-[var(--theme-color-10)]');
                        },
                        dropTask(event, taskId) {
                            let taskNode = event.currentTarget;
                            taskNode.classList.remove('border-[var(--theme-color)]', 'bg-[var(--theme-color-10)]');
                            if(this.draggedAgentId && taskId) {
                                try { @this.assignAgent(taskId, this.draggedAgentId); } catch(e) {}
                            }
                            this.draggedAgentId = null;
                        }
                    }));
                } catch (e) {
                    console.warn('workspaceCanvas registration skipped or failed:', e);
                }
                
                if (!window.workspaceCanvasDragRegistered) {
                    window.workspaceCanvasDragRegistered = true;
                    document.addEventListener('dragend', () => {
                        document.querySelectorAll('.agent-draggable').forEach(el => el.classList.remove('opacity-30'));
                    });
                }
            };

            if (window.Alpine) {
                initCanvas();
            } else {
                document.addEventListener('alpine:init', initCanvas);
            }
        })();
    </script>
    
    <!-- Marked.js, DOMPurify, Highlight.js for Chat Markdown -->
    <script src="{{ asset('vendor/marked/marked.min.js') }}"></script>
    <script src="{{ asset('vendor/dompurify/purify.min.js') }}"></script>
    <script src="{{ asset('vendor/highlight/highlight.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/highlight/github-dark.min.css') }}">
    
    <script>
        (function() {
            const initMarkdown = () => {
                if (typeof marked !== 'undefined') {
                    marked.setOptions({
                        highlight: function(code, lang) {
                            const language = hljs.getLanguage(lang) ? lang : 'plaintext';
                            return hljs.highlight(code, { language }).value;
                        },
                        gfm: true, breaks: true
                    });
                    const renderer = new marked.Renderer();
                    
                    renderer.code = function(...args) {
                        let token = typeof args[0] === 'object' ? args[0] : null;
                        let code = token ? token.text : args[0];
                        let lang = token ? token.lang : args[1];
                        let highlightedCode = '';
                        try { highlightedCode = hljs.highlight(code, { language: hljs.getLanguage(lang) ? lang : 'plaintext' }).value; } 
                        catch(e) { highlightedCode = code.replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
                        return `<div class="my-3 rounded-xl overflow-hidden border border-gray-800 bg-gray-950 text-xs font-mono max-w-full"><div class="px-3 py-1.5 bg-gray-900 border-b border-gray-800"><span class="text-gray-500 uppercase">${lang||'code'}</span></div><div class="p-4 overflow-x-auto custom-scrollbar max-w-full"><pre class="!bg-transparent !m-0 !p-0"><code class="hljs text-gray-300 leading-relaxed">${highlightedCode}</code></pre></div></div>`;
                    };
                    
                    window.renderAiMarkdown = function(md) {
                        const html = marked.parse(md, { renderer });
                        return DOMPurify.sanitize(html);
                    };
                    console.log('window.renderAiMarkdown initialized successfully.');
                }
            };

            if (typeof marked !== 'undefined') {
                initMarkdown();
            } else {
                let checkCount = 0;
                const interval = setInterval(() => {
                    checkCount++;
                    if (typeof marked !== 'undefined') {
                        initMarkdown();
                        clearInterval(interval);
                    } else if (checkCount > 100) {
                        clearInterval(interval);
                    }
                }, 50);
            }
        })();
    </script>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--theme-color-30); border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--theme-color-50); }
        .ai-markdown-content p { margin-bottom: 0.5rem; }
        .ai-markdown-content ul { list-style-type: disc; padding-left: 1.5rem; }
        .ai-markdown-content h3 { font-weight: bold; margin-top: 1rem; color: #fff; }
        .ai-markdown-content code:not(.hljs) { background-color: rgba(255,255,255,0.1); padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-family: monospace; font-size: 0.85em; color: var(--theme-color); }
    </style>

    <!-- Management Modals (Isolated via wire:ignore) -->
    <div wire:ignore>
        <!-- Role Manager Modal -->
        <div x-data="{ showRoleManager: false }" 
             x-on:open-role-manager.window="
                showRoleManager = true; 
                if($event.detail.roleId) { 
                    Livewire.dispatchTo('shop.ai.ai-role-manager', 'edit-role', { roleId: $event.detail.roleId }); 
                } else { 
                    Livewire.dispatchTo('shop.ai.ai-role-manager', 'edit-role', { roleId: null }); 
                }
             ">
            
            <div x-show="showRoleManager" style="display: none;" 
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity duration-300" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                
                <div class="relative w-[95vw] h-[95vh] lg:w-[90vw] lg:h-[90vh] bg-gray-950 border border-emerald-500/30 rounded-3xl overflow-auto custom-scrollbar shadow-2xl flex flex-col" 
                     x-transition:enter="ease-out duration-300 transform"
                     x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="ease-in duration-200 transform"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                     @click.away="showRoleManager = false">
                    
                    <button @click="showRoleManager = false" class="absolute top-4 right-4 z-50 text-gray-400 hover:text-white p-2 bg-gray-900/50 rounded-full border border-gray-700/50 hover:bg-gray-800 transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                    
                    <div class="flex-1 w-full relative">
                        <livewire:shop.ai.ai-role-manager />
                    </div>
                </div>
            </div>
        </div>

        <!-- Internal Agent Editor Modal -->
        <div x-data="{ showAgentManager: false }" 
             x-on:open-agent-manager.window="
                showAgentManager = true; 
                let aid = $event.detail.agentId || 'new';
                Livewire.dispatchTo('shop.ai.ai-agent-editor', 'edit-agent', { id: aid }); 
             "
             x-on:close-agent-manager.window="showAgentManager = false">
            
            <div x-show="showAgentManager" style="display: none;" 
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity duration-300" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                
                <div class="relative w-[95vw] h-[95vh] lg:w-[90vw] lg:h-[90vh] bg-[#050505] border border-gray-800 rounded-3xl overflow-auto custom-scrollbar shadow-[0_0_50px_rgba(0,0,0,1)] flex flex-col pt-10" 
                     x-transition:enter="ease-out duration-300 transform"
                     x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="ease-in duration-200 transform"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                     @click.away="if(!document.querySelector('.ck-body-wrapper')) showAgentManager = false">
                    
                    <button @click="showAgentManager = false" class="absolute top-4 right-4 z-50 text-gray-400 hover:text-white p-2 bg-gray-900/50 rounded-full border border-gray-700/50 hover:bg-gray-800 transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                    
                    <div class="flex-1 w-full relative">
                        <livewire:shop.ai.ai-agent-editor />
                    </div>
                </div>
            </div>
        </div>
    </div>
