
            <!-- AI Chat Console -->
            <div wire:key="tab-chat" x-data="{ showMobileChatSidebar: false }" :class="{'hidden': activeTab !== 'chat', '!fixed !inset-0 !m-0 !p-0 !z-[99999] !h-[100dvh] !w-[100vw] !rounded-none !border-none !bg-gray-950': isChatFullScreen}" class="flex-1 shrink-0 rounded-2xl border border-gray-800 bg-gray-900/80 backdrop-blur-xl flex overflow-hidden relative shadow-[0_0_30px_rgba(0,0,0,0.5)] h-full w-full">

                
                <!-- Burger Menu Sidebar (Desktop/Tablet) -->
                <div class="hidden md:flex border-r border-gray-800" x-show="!isChatFullScreen">
                    <x-backend.ai-chat-sidebar />
                </div>
                
                <div class="flex-1 flex flex-col min-h-0 relative">
                
                <!-- Mobile Sidebar Toggle -->
                <button @click="showMobileChatSidebar = true" class="md:hidden absolute top-4 left-4 z-50 text-gray-400 hover:text-white transition-colors bg-gray-900/80 hover:bg-gray-800 p-2 rounded-xl backdrop-blur-md border border-gray-700 shadow-xl" title="Chats anzeigen">
                    <x-heroicon-o-bars-3-bottom-left class="w-4 h-4" />
                </button>

                <!-- Fullscreen Toggle Button (Mobile) -->
                <button @click="isChatFullScreen = !isChatFullScreen" class="lg:hidden absolute top-4 right-4 z-50 text-gray-400 hover:text-white transition-colors bg-gray-900/80 hover:bg-gray-800 p-2 rounded-xl backdrop-blur-md border border-gray-700 shadow-xl" title="Chat maximieren">
                    <svg x-show="!isChatFullScreen" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                    </svg>
                    <svg style="display: none;" x-show="isChatFullScreen" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                    </svg>
                </button>
                
                <!-- Chat Log Area -->
                <div id="chat-scroll-container" class="flex-1 overflow-y-auto p-4 lg:p-6 pt-16 lg:pt-6 space-y-6 custom-scrollbar scroll-smooth"
                     x-data="{
                         scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight; },
                         observeScroll() {
                             const observer = new MutationObserver(() => {
                                 if (this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 300) {
                                     this.scrollToBottom();
                                 }
                             });
                             observer.observe(this.$el, { childList: true, subtree: true, characterData: true });
                         }
                     }"
                     x-init="setTimeout(() => { scrollToBottom(); observeScroll(); }, 50);">
                     
                    @forelse($this->messages as $msg)
                        <div class="flex flex-col {{ $msg['role'] === 'user' ? 'items-end' : 'items-start' }} animate-fade-in-up" wire:key="msg-key-{{ md5(substr($msg['content'], 0, 50) . $loop->index) }}">
                            <div class="flex items-center gap-3 mb-1.5 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                                <div class="w-10 h-10 rounded shrink-0 flex justify-center items-center {{ $msg['color'] ? 'bg-'.$msg['color'].'/10 border-'.$msg['color'].'/40 text-'.$msg['color'] : 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-[var(--theme-color)]' }} shadow-[0_0_10px_currentColor] overflow-hidden">
                                    @if(isset($msg['profile_picture']) && $msg['profile_picture'])
                                        @php
                                            $pp = $msg['profile_picture'];
                                            $src = (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/'))
                                                   ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                                        @endphp
                                        <img src="{{ $src }}" class="w-full h-full object-cover" alt="Profile">
                                    @else
                                        <x-dynamic-component :component="'heroicon-o-' . str_replace(['bi-stars', 'bi-'], ['sparkles', ''], ($msg['icon'] ?: 'cpu-chip'))" class="w-6 h-6" />
                                    @endif
                                </div>
                                <span class="text-xs font-bold {{ $msg['color'] ? 'text-'.$msg['color'] : 'text-[var(--theme-color)]' }} tracking-widest uppercase truncate max-w-[200px]">{{ $msg['name'] }}</span>
                            </div>
                            <div class="max-w-[90%] lg:max-w-[85%] min-w-0 text-sm lg:text-base leading-relaxed p-3 px-4 rounded-xl {{ $msg['role'] === 'user' ? 'bg-gray-950 border border-gray-700 text-gray-300 rounded-tr-none shadow-md' : 'bg-[var(--theme-color-10)] text-gray-200 rounded-tl-none border border-gray-800 shadow-xl shadow-[var(--theme-color-10)]' }}">
                                @if($msg['role'] === 'user')
                                    @if(!empty($msg['attachments']) || !empty($msg['local_uploads']))
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            @if(!empty($msg['attachments']))
                                                @foreach($msg['attachments'] as $att)
                                                    <div class="flex items-center gap-1 bg-gray-900 border border-gray-700 text-emerald-400 text-[10px] uppercase font-bold tracking-widest px-2 py-0.5 rounded shadow-inner">
                                                        <button class="text-gray-500 hover:text-white transition-colors" title="Nachricht bearbeiten">
                                                            <x-heroicon-o-pencil class="w-4 h-4" />
                                                        </button>
                                                        <x-heroicon-o-document-text class="w-3 h-3" />
                                                        <span>{{ basename($att) }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                            @if(!empty($msg['local_uploads']))
                                                @foreach($msg['local_uploads'] as $file)
                                                    @php $fileUrl = isset($file['path']) ? Storage::url($file['path']) : '#'; @endphp
                                                    <a href="{{ $fileUrl }}" target="_blank" class="flex items-center gap-1 bg-gray-900 border border-gray-700 text-[var(--theme-color)] text-[10px] uppercase font-bold tracking-widest px-2 py-0.5 rounded shadow-inner hover:bg-[var(--theme-color-10)] transition-colors cursor-pointer">
                                                        <x-heroicon-o-paper-clip class="w-3 h-3 shrink-0" />
                                                        <span class="truncate max-w-[150px]">{{ $file['name'] ?? 'Upload' }}</span>
                                                    </a>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif
                                    <div class="font-mono">{!! nl2br(e($msg['content'])) !!}</div>
                                @else
                                    <div class="ai-markdown-wrapper w-full custom-scrollbar font-sans" x-data="{ expanded: false, content: @js($msg['content']) }">
                                        <div class="relative">
                                            <div class="overflow-hidden transition-all duration-300 ai-markdown-content" 
                                                 :style="expanded ? 'max-height: none;' : (content.length > 300 ? 'max-height: 150px;' : 'max-height: none;')"
                                                 x-init="
                                                    const cleanContent = content.replace(/<speak>/gi, '').replace(/<\/speak>/gi, '');
                                                    const render = () => { $el.innerHTML = window.renderAiMarkdown(cleanContent); };
                                                    if (window.renderAiMarkdown) { render(); } else { setTimeout(render, 500); }
                                                 " wire:ignore>
                                            </div>
                                            <div x-show="!expanded && content.length > 300" class="absolute bottom-0 left-0 right-0 h-12 bg-gradient-to-t from-gray-900/80 to-transparent pointer-events-none"></div>
                                        </div>
                                        <button x-show="content.length > 300" @click="expanded = !expanded" class="text-[10px] uppercase font-bold tracking-widest text-[var(--theme-color)] mt-3 hover:text-white transition-colors flex items-center gap-1">
                                            <span x-text="expanded ? 'Weniger anzeigen' : 'Vollständige Nachricht lesen'"></span>
                                            <x-heroicon-s-chevron-down class="w-3 h-3 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-gray-400/40 font-mono tracking-widest gap-4">
                            <x-heroicon-o-chat-bubble-left-right class="w-12 h-12 opacity-50" />
                            <p>Keine aktiven Gespräche. Der Raum ist still.</p>
                        </div>
                    @endforelse

                    <!-- Typing Indicators -->
                    @foreach($typingAgents as $tId)
                        @php $tAgent = $agents->firstWhere('id', $tId); @endphp
                        @if($tAgent)
                        <div class="flex flex-col items-start animate-fade-in-up" wire:key="typing-{{ $tId }}">
                            <div class="flex items-center gap-3 mb-1.5 ">
                                <div class="w-10 h-10 rounded shrink-0 flex justify-center items-center bg-{{ $tAgent->color }}/10 border border-{{ $tAgent->color }}/40 shadow-[0_0_10px_currentColor] text-{{ $tAgent->color }} overflow-hidden">
                                     @if($tAgent->profile_picture)
                                         <img src="{{ \Illuminate\Support\Str::startsWith($tAgent->profile_picture, 'shop/') || \Illuminate\Support\Str::startsWith($tAgent->profile_picture, 'shopverwaltung/images/') || \Illuminate\Support\Str::startsWith($tAgent->profile_picture, '/') ? asset($tAgent->profile_picture) : Storage::url($tAgent->profile_picture) }}" class="w-full h-full object-cover">
                                     @else
                                         <x-dynamic-component :component="'heroicon-o-' . str_replace(['bi-stars', 'bi-'], ['sparkles', ''], ($tAgent->icon ?: 'cpu-chip'))" class="w-6 h-6" />
                                     @endif
                                </div>
                                <span class="text-xs font-bold text-{{ $tAgent->color }} tracking-widest uppercase">{{ $tAgent->name }}</span>
                            </div>
                            <div class="max-w-[85%] px-5 py-3 rounded-xl bg-[var(--theme-color-10)] text-gray-200 rounded-tl-none border border-gray-800 shadow-xl shadow-[var(--theme-color-10)]">
                                <div class="font-mono text-sm leading-relaxed flex items-center gap-3">
                                    <span class="flex gap-1.5 pt-1">
                                        <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                                        <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                                        <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce"></span>
                                    </span>
                                    <span class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-gray-500 uppercase tracking-widest animate-pulse-slow font-bold">Tippe...</span>
                                        <button type="button" wire:click="abortInference('{{ $tId }}')" title="Denkprozess sofort abbrechen" class="w-4 h-4 rounded bg-red-500/10 border border-red-500/30 text-red-500 hover:bg-red-500/30 hover:text-red-400 flex items-center justify-center transition-colors">
                                            <x-heroicon-s-x-mark class="w-3 h-3" />
                                        </button>
                                    </span>
                                </div>
                                <div wire:stream="thought_{{ $tId }}" class="mt-2 text-xs flex flex-col gap-1 font-mono text-[var(--theme-color)] opacity-70 empty:hidden empty:mt-0"></div>
                                <div wire:stream="answer_{{ $tId }}" class="mt-3 text-sm font-sans text-gray-200 leading-relaxed whitespace-pre-wrap empty:hidden empty:mt-0"></div>
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>

                <!-- Input Area / Form -->
                <div class="p-3 bg-gray-950 border-t border-gray-800 z-20 shrink-0 relative"
                     x-data="{ isDropping: false }"
                     x-on:dragover.prevent="isDropping = true"
                     x-on:dragleave.prevent="isDropping = false"
                     x-on:drop.prevent="isDropping = false; $wire.uploadMultiple('uploadedFiles', $event.dataTransfer.files)">
                     
                    <!-- Drop Overlay -->
                    <div x-show="isDropping" style="display: none;" class="absolute inset-0 bg-[var(--theme-color-10)] border-2 border-dashed border-[var(--theme-color)] z-50 flex items-center justify-center backdrop-blur-sm m-2 rounded-xl">
                        <div class="text-center">
                            <x-heroicon-o-cloud-arrow-up class="w-12 h-12 text-[var(--theme-color)] mx-auto mb-2 animate-bounce" />
                            <span class="text-xl font-bold text-[var(--theme-color)] drop-shadow-md">Dateien ablegen</span>
                        </div>
                    </div>

                    <div class="mx-auto relative" x-data="{
                        async insertMention(filePath) {
                            let inputEl = document.getElementById('workspaceChatInput');
                            let val = inputEl.value;
                            let pos = inputEl.selectionStart;
                            let lastAt = val.lastIndexOf('@', pos - 1);
                            if (lastAt !== -1) {
                                let newValue = val.substring(0, lastAt) + val.substring(pos);
                                $wire.set('input', newValue);
                                await $wire.addAttachment(filePath);
                                requestAnimationFrame(() => {
                                    inputEl.value = newValue;
                                    inputEl.focus();
                                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                                });
                            }
                        },
                        checkMention() {
                            let inputEl = document.getElementById('workspaceChatInput');
                            if (!inputEl) return;
                            let val = inputEl.value;
                            let pos = inputEl.selectionStart;
                            let lastAt = val.lastIndexOf('@', pos - 1);
                            if (lastAt !== -1 && (lastAt === 0 || val[lastAt-1].match(/\s/))) {
                                let query = val.substring(lastAt + 1, pos);
                                if (!query.includes(' ') && !query.includes('\n')) {
                                    $wire.searchFilesForMention(query);
                                    return;
                                }
                            }
                            if ($wire.mentionQuery !== '') $wire.searchFilesForMention('');
                        },
                        handleEnter(e) {
                            if (e.shiftKey) return;
                            e.preventDefault();
                            
                            // Check if a mention dropdown is currently visible
                            let fm = document.querySelector('#mention-dropdown .mention-item');
                            if (fm) {
                                fm.click();
                                return;
                            }
                            
                            // Check if actively typing a mention (to prevent accidental send before dropdown loads)
                            let val = e.target.value;
                            let pos = e.target.selectionStart;
                            let lastAt = val.lastIndexOf('@', pos - 1);
                            if (lastAt !== -1 && (lastAt === 0 || val[lastAt-1].match(/\s/))) {
                                let query = val.substring(lastAt + 1, pos);
                                if (!query.includes(' ') && !query.includes('\n')) {
                                    // User is typing a mention but dropdown hasn't loaded yet. Do nothing!
                                    return;
                                }
                            }
                            
                            // Send message
                            $wire.sendMessage();
                        }
                    }">

                        <!-- Attachment Badges -->
                        @if(!empty($attachments) || !empty($uploadedFiles))
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach($attachments as $idx => $att)
                                <div wire:key="att-{{ md5($att . $idx) }}" class="flex items-center gap-1 bg-gray-900 border border-gray-700 text-gray-300 text-xs px-2 py-1 rounded">
                                    <x-heroicon-o-document-text class="w-3 h-3" />
                                    <span>{{ basename($att) }}</span>
                                    <button type="button" wire:click="removeAttachment('{{ $idx }}')" class="hover:text-red-400 ml-1"><x-heroicon-s-x-mark class="w-3.5 h-3.5" /></button>
                                </div>
                            @endforeach
                            @foreach($uploadedFiles as $idx => $file)
                                <div wire:key="up-{{ $idx }}" class="flex items-center gap-1 bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] text-xs px-2 py-1 rounded">
                                    <x-heroicon-o-paper-clip class="w-3 h-3" />
                                    <span>{{ is_object($file) ? $file->getClientOriginalName() : 'Uploading...' }}</span>
                                    <button type="button" wire:click="removeUploadedFile('{{ $idx }}')" class="hover:text-red-400 ml-1"><x-heroicon-s-x-mark class="w-3.5 h-3.5" /></button>
                                </div>
                            @endforeach
                        </div>
                        @endif

                        <!-- Mention Dropdown -->
                        @if(!empty($mentionResults))
                        <div id="mention-dropdown" class="absolute bottom-[calc(100%+0.5rem)] left-0 w-80 max-h-60 overflow-y-auto custom-scrollbar bg-gray-900 border border-[var(--theme-color-40)] rounded-xl shadow-2xl z-50">
                            <div class="text-[10px] text-gray-500 uppercase px-3 py-1 bg-gray-950 border-b border-gray-800">Dateien einfügen</div>
                            @foreach($mentionResults as $result)
                                <button type="button" @click="insertMention('{{ addslashes($result) }}')" class="mention-item w-full text-left px-3 py-2 hover:bg-[var(--theme-color-20)] border-b border-gray-800/50">
                                    <div class="flex items-center gap-2 text-sm text-gray-300 font-mono"><x-heroicon-o-document-text class="w-4 h-4 shrink-0" /><span class="truncate">{{ basename($result) }}</span></div>
                                    <div class="text-[10px] text-gray-500 truncate ml-6">{{ dirname($result) }}</div>
                                </button>
                            @endforeach
                        </div>
                        @endif

                        <form wire:submit.prevent="sendMessage" class="relative w-full">
                            <!-- Clipboard Permission Button -->
                            <button x-show="clipboardNeedsPermission" x-cloak @click.prevent="readClipboard(true)" 
                                    class="absolute left-0 -top-12 z-20 flex items-center gap-2 px-3 py-1.5 rounded-lg border border-rose-500/50 bg-rose-500/20 text-rose-400 shadow-[0_0_15px_rgba(244,63,94,0.3)] animate-pulse hover:bg-rose-500/30 transition-all font-bold tracking-widest text-[10px] uppercase text-center backdrop-blur-md"
                                    title="Zwischenspeicher freigeben">
                                <x-heroicon-s-camera class="w-4 h-4 shrink-0" />
                                <span>Klicken für Freigabe</span>
                            </button>

                            <div class="absolute left-2 top-1/2 -translate-y-1/2 flex items-center z-10">
                                <label class="cursor-pointer text-gray-500 hover:text-[var(--theme-color)] p-1">
                                    <x-heroicon-o-paper-clip class="w-5 h-5" />
                                    <input type="file" wire:model="uploadedFiles" multiple class="hidden" />
                                </label>
                            </div>
                            <textarea id="workspaceChatInput" wire:model="input"
                                   @keydown.enter="handleEnter($event)"
                                   @keyup="checkMention" @click="checkMention"
                                   rows="1"
                                   style="padding-left: 90px;"
                                   class="w-full bg-gray-900 border border-gray-800 rounded-lg pr-12 py-3 text-[var(--theme-color)] focus:border-[var(--theme-color)] focus:ring-[var(--theme-color-30)] text-sm shadow-inner font-sans outline-none resize-none custom-scrollbar" 
                                   placeholder="Nachricht eingeben..." autofocus></textarea>
                                   
                            <button type="button" wire:click="createTaskFromChat" title="Als neue Aufgabe auf dem Board ablegen" class="absolute left-10 top-1/2 -translate-y-1/2 h-8 w-10 z-10 bg-emerald-500/10 border border-emerald-500/30 rounded-md hover:bg-emerald-500/20 text-emerald-500 flex justify-center items-center transition-all cursor-pointer">
                                <x-heroicon-o-queue-list class="w-5 h-5" />
                            </button>
                                   
                            <button type="submit" title="An die KI senden" class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 z-10 bg-[var(--theme-color-20)] border border-[var(--theme-color-50)] rounded-md hover:bg-[var(--theme-color-40)] text-[var(--theme-color)] flex justify-center items-center transition-all cursor-pointer">
                                <x-heroicon-s-paper-airplane class="w-4 h-4 hover:translate-x-0.5" />
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Mobile Chat Sidebar (Off-canvas) -->
                <div x-show="showMobileChatSidebar" class="md:hidden absolute inset-0 z-[99999] flex" style="display: none;">
                    <div x-show="showMobileChatSidebar" x-transition.opacity class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showMobileChatSidebar = false"></div>
                    <div x-show="showMobileChatSidebar" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="relative w-[85%] max-w-sm h-full shadow-2xl flex flex-col pointer-events-auto z-10 border-r border-[var(--theme-color-50)]">
                        <x-backend.ai-chat-sidebar class="h-full border-r-0 w-full" />
                        <!-- Close Button Inside Sidebar -->
                        <button @click="showMobileChatSidebar = false" class="absolute top-4 right-4 bg-gray-900 border border-[var(--theme-color-50)] text-gray-300 hover:text-white p-2 rounded-xl shadow-[0_0_15px_rgba(0,0,0,0.5)] z-50">
                            <x-heroicon-s-x-mark class="w-5 h-5" />
                        </button>
                    </div>
                </div>

            </div>
</div>
