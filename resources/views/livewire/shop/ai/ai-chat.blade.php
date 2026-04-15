<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 flex flex-col transition-all duration-300 relative"
     :class="isFullscreen ? 'fixed inset-0 z-[100] !h-[100dvh] !w-full !max-w-none !p-0 !m-0 bg-gray-950/95 backdrop-blur-3xl' : 'h-[calc(100vh-2rem)]'"
     x-data="{
        isFullscreen: false,
        activeTab: 'chat',
        init() {
            this.scrollToBottom();
            $wire.$watch('messages', () => { setTimeout(() => this.scrollToBottom(), 50) });
            $wire.$watch('typingAgents', () => { setTimeout(() => this.scrollToBottom(), 50) });
        },
        scrollToBottom() {
            let el = document.getElementById('chat-scroll-container');
            if(el) el.scrollTop = el.scrollHeight;
        }
     }"
     x-on:start-ai-inference.window="
        $event.detail.agentIds.forEach(id => {
            $wire.processAgent(id);
        });
     ">
    <!-- Header -->
    <div class="mb-5 text-center mt-4 font-sans transition-all duration-300"
         :class="isFullscreen ? 'hidden' : 'px-4 sm:px-0'">
        <h1 class="text-3xl sm:text-4xl font-black text-[var(--theme-color)] drop-shadow-sm">
            KI Chat Konsole
        </h1>
        <p class="text-gray-400 mt-2 text-sm">
            Multi-Agenten System Interaktives Terminal
        </p>
    </div>

    <!-- Matrix Chat Terminal -->
    <div class="flex-1 bg-gray-900/80 backdrop-blur-xl border border-gray-800 rounded-xl shadow-xl shadow-[var(--theme-color-10)] flex flex-col overflow-hidden backdrop-blur-xl transition-all duration-300"
         :class="isFullscreen ? '!border-0 !rounded-none' : ''">
        <!-- Terminal Header -->
        <div class="bg-[var(--theme-color-10)] border-b border-gray-800 p-3 flex flex-col md:flex-row justify-between md:items-center gap-3 relative overflow-visible shrink-0">
            <div class="absolute inset-0 bg-gradient-to-r from-[var(--theme-color-10)] to-transparent pointer-events-none"></div>

            <div class="flex justify-between w-full md:w-auto items-center gap-4 relative z-10 shrink-0">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-command-line class="w-6 h-6 text-[var(--theme-color)] animate-pulse hidden sm:block" />
                    <div>
                        <span class="text-[var(--theme-color)] font-bold text-xs sm:text-sm tracking-widest uppercase shadow-[var(--theme-color)] block leading-tight">Verschlüsselte Übertragung</span>
                        <span class="text-gray-400 text-[10px] font-mono uppercase tracking-widest">Verfügbare Agenten</span>
                    </div>
                </div>

                <!-- Mobile Actions Top Right -->
                <div class="flex md:hidden items-center gap-3 text-gray-400">
                    <button @click="isFullscreen = !isFullscreen" title="Vollbild" class="hover:text-[var(--theme-color)] transition-colors">
                        <x-heroicon-s-arrows-pointing-out class="w-5 h-5 drop-shadow-md" x-show="!isFullscreen" />
                        <x-heroicon-s-arrows-pointing-in class="w-5 h-5 drop-shadow-md" x-show="isFullscreen" style="display: none;" />
                    </button>
                    <button wire:click="clearChat" wire:confirm="Sicher, dass du den Chat-Verlauf restlos wipen möchtest?" class="text-red-900/60 hover:text-red-500 transition-colors">
                        <x-heroicon-o-trash class="w-5 h-5 drop-shadow-md" />
                    </button>
                    <button @click="$dispatch('open-profile-modal', {tab: '2fa'})" class="hover:text-[var(--theme-color)]">
                        <x-heroicon-s-shield-check class="w-5 h-5 drop-shadow-md" />
                    </button>
                </div>
            </div>

            <!-- Agenten Auswahl (Miniatur) Fließend im Header -->
            <div class="flex-1 min-w-0 relative z-10 flex">
                <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-1 pt-1 w-full pl-1 md:pl-4">
                    @foreach($agents as $agent)
                        @php
                            $isActive = in_array($agent->id, $activeAgentIds);
                        @endphp
                        <button wire:click="toggleAgent('{{ $agent->id }}')"
                                title="{{ $agent->name }}"
                                class="shrink-0 relative group rounded-full border transition-all duration-300 flex items-center gap-2 pr-4 bg-gray-900/50 backdrop-blur-xl {{ $isActive ? 'border-[var(--theme-color)]/80 shadow-xl shadow-[var(--theme-color-10)] bg-[var(--theme-color-10)]' : 'border-gray-800 hover:border-[var(--theme-color-30)] opacity-70 hover:opacity-100' }}">

                            <div class="relative w-8 h-8 rounded-full bg-{{ $agent->color }}/10 flex items-center justify-center text-{{ $agent->color }} overflow-hidden">
                                @if($agent->profile_picture)
                                    <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" alt="{{ $agent->name }}" class="w-full h-full object-cover">
                                @else
                                    @if(str_starts_with($agent->icon, 'bi-'))
                                        <i class="{{ $agent->icon }} text-base drop-shadow-[0_0_5px_currentColor]"></i>
                                    @elseif(str_starts_with(trim($agent->icon), '<svg'))
                                        <div class="w-4 h-4 [&>svg]:w-full [&>svg]:h-full drop-shadow-[0_0_5px_currentColor]">{!! $agent->icon !!}</div>
                                    @else
                                        <x-dynamic-component :component="'heroicon-o-' . ($agent->icon ?: 'cpu-chip')" class="w-4 h-4" />
                                    @endif
                                @endif
                            </div>
                            <!-- Status Dot -->
                            <span class="absolute top-0 right-0 -mt-0.5 -mr-0.5 flex h-2.5 w-2.5">
                                @if($isActive)
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--theme-color)] opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[var(--theme-color)] border border-black"></span>
                                @else
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-600 border border-black"></span>
                                @endif
                            </span>

                            <span class="text-[10px] font-bold font-mono tracking-wider {{ $isActive ? 'text-'.$agent->color : 'text-gray-400' }} truncate max-w-[80px]">
                                {{ explode(' ', $agent->name)[0] }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Desktop Actions -->
            <div class="hidden md:flex items-center justify-end gap-3 relative z-10 text-gray-400 shrink-0">
                <button @click="isFullscreen = !isFullscreen" title="Vollbild" class="hover:text-[var(--theme-color)] transition-colors">
                    <x-heroicon-s-arrows-pointing-out class="w-5 h-5 drop-shadow-md" x-show="!isFullscreen" />
                    <x-heroicon-s-arrows-pointing-in class="w-5 h-5 drop-shadow-md" x-show="isFullscreen" style="display: none;" />
                </button>
                <a href="/admin/global-logs" title="System Logs" class="hover:text-[var(--theme-color)] transition-colors">
                    <x-heroicon-s-server-stack class="w-5 h-5 drop-shadow-md" />
                </a>
                <button @click="$dispatch('open-profile-modal', {tab: '2fa'})" title="Sicherheits-Firewall" class="hover:text-[var(--theme-color)] transition-colors">
                    <x-heroicon-s-shield-check class="w-5 h-5 drop-shadow-md" />
                </button>
                <div class="w-px h-5 bg-gray-800 mx-2"></div>
                <button wire:click="clearChat" wire:confirm="Sicher, dass du den Chat-Verlauf restlos wipen möchtest?" title="Chat-Verlauf leeren" class="text-red-900/60 hover:text-red-500 transition-colors hover:scale-110">
                    <x-heroicon-o-trash class="w-5 h-5 drop-shadow-md" />
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-gray-900 border-b border-gray-800 px-4 flex gap-6 text-sm tracking-wider font-mono">
            <button @click="activeTab = 'chat'; setTimeout(() => scrollToBottom(), 50)" 
                    class="py-3 px-2 relative transition-colors border-b-2"
                    :class="activeTab === 'chat' ? 'text-[var(--theme-color)] border-[var(--theme-color)]' : 'text-gray-500 border-transparent hover:text-gray-300'">
                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4 inline-block mr-1"/> Chat
            </button>
            <button @click="activeTab = 'plans'" 
                    class="py-3 px-2 relative transition-colors border-b-2"
                    :class="activeTab === 'plans' ? 'text-[var(--theme-color)] border-[var(--theme-color)]' : 'text-gray-500 border-transparent hover:text-gray-300'">
                <x-heroicon-o-document-text class="w-4 h-4 inline-block mr-1"/> Workspace Pläne
                @if(count($this->artifacts) > 0)
                    <span class="ml-1 bg-[var(--theme-color)] text-black text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ count($this->artifacts) }}</span>
                @endif
            </button>
            <button @click="activeTab = 'files'" 
                    class="py-3 px-2 relative transition-colors border-b-2"
                    :class="activeTab === 'files' ? 'text-[var(--theme-color)] border-[var(--theme-color)]' : 'text-gray-500 border-transparent hover:text-gray-300'">
                <x-heroicon-o-folder class="w-4 h-4 inline-block mr-1"/> Dateimanager
                @if(count($this->globalFiles) > 0)
                    <span class="ml-1 bg-gray-700 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ count($this->globalFiles) }}</span>
                @endif
            </button>
        </div>

        <!-- Messages Area (Chat Tab) -->
        <div x-show="activeTab === 'chat'" class="flex-1 flex flex-col overflow-hidden">
            <div id="chat-scroll-container" 
                 class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar scroll-smooth"
                 x-data="{
                     scrollToBottom() {
                         this.$el.scrollTop = this.$el.scrollHeight;
                     },
                     observeScroll() {
                         const observer = new MutationObserver(() => {
                             // Only auto-scroll if we're near the bottom (allows user to scroll back up to read)
                             // Or if it's the very first load
                             if (this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight < 300) {
                                 this.scrollToBottom();
                             }
                         });
                         observer.observe(this.$el, { childList: true, subtree: true, characterData: true });
                     }
                 }"
                 x-init="setTimeout(() => { scrollToBottom(); observeScroll(); }, 50);">
            @forelse($messages as $msg)
                <div class="flex flex-col {{ $msg['role'] === 'user' ? 'items-end' : 'items-start' }} animate-fade-in-up" wire:key="msg-key-{{ md5(substr($msg['content'], 0, 50) . $loop->index) }}">
                    <div class="flex items-center gap-3 mb-1.5 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                        <div class="w-12 h-12 rounded shrink-0 flex justify-center items-center {{ $msg['color'] ? 'bg-'.$msg['color'].'/10 border-'.$msg['color'].'/40 text-'.$msg['color'] : 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-[var(--theme-color)]' }} shadow-[0_0_10px_currentColor] overflow-hidden">
                            @if(isset($msg['profile_picture']) && $msg['profile_picture'])
                                @php
                                    $pp = $msg['profile_picture'];
                                    $src = (str_starts_with($pp, 'images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/'))
                                           ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                                @endphp
                                <img src="{{ $src }}" class="w-full h-full object-cover" alt="Profile">
                            @else
                                <x-dynamic-component :component="'heroicon-o-' . ($msg['icon'] ?: 'cpu-chip')" class="w-7 h-7" />
                            @endif
                        </div>
                        <span class="text-xs font-bold {{ $msg['color'] ? 'text-'.$msg['color'] : 'text-[var(--theme-color)]' }} tracking-widest uppercase truncate max-w-[200px]">{{ $msg['name'] }}</span>
                    </div>
                    <div class="max-w-[85%] text-base leading-relaxed p-4 rounded-xl {{ $msg['role'] === 'user' ? 'bg-gray-950 border border-gray-700 text-gray-300 rounded-tr-none shadow-md' : 'bg-[var(--theme-color-10)] text-gray-200 rounded-tl-none border border-gray-800 shadow-xl shadow-[var(--theme-color-10)]' }}">
                        @php
                            $getIconProps = function($filename) {
                                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                if (str_ends_with(strtolower($filename), '.blade.php')) $ext = 'blade';
                                
                                return match($ext) {
                                    'php' => ['icon' => 'heroicon-o-code-bracket-square', 'class' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/30'],
                                    'blade' => ['icon' => 'heroicon-o-rectangle-group', 'class' => 'text-orange-400 border-orange-900/50 bg-orange-950/30'],
                                    'js', 'ts', 'vue', 'json' => ['icon' => 'heroicon-o-command-line', 'class' => 'text-yellow-400 border-yellow-900/50 bg-yellow-950/30'],
                                    'css', 'scss', 'html' => ['icon' => 'heroicon-o-globe-alt', 'class' => 'text-sky-400 border-sky-900/50 bg-sky-950/30'],
                                    'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => ['icon' => 'heroicon-o-photo', 'class' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/30'],
                                    'pdf', 'csv', 'xlsx' => ['icon' => 'heroicon-o-table-cells', 'class' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/30'],
                                    default => ['icon' => 'heroicon-o-document-text', 'class' => 'text-gray-400 border-gray-700 bg-gray-900']
                                };
                            };
                        @endphp
                        @if($msg['role'] === 'user')
                            <div class="font-mono">{!! nl2br(e($msg['content'])) !!}</div>
                            
                            @if(!empty($msg['attachments']) || !empty($msg['local_uploads']))
                                <div class="mt-3 flex flex-wrap gap-2 pt-2 border-t border-gray-800 border-dashed">
                                    @if(!empty($msg['attachments']))
                                        @foreach($msg['attachments'] as $att)
                                            @php $props = $getIconProps($att); @endphp
                                            <div class="flex items-center gap-1.5 border px-2 py-1 rounded {{ $props['class'] }} text-xs transition-colors hover:bg-opacity-50">
                                                @svg($props['icon'], 'w-3.5 h-3.5')
                                                <span class="font-mono">{{ basename($att) }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                    @if(!empty($msg['local_uploads']))
                                        @foreach($msg['local_uploads'] as $up)
                                            @php $props = $getIconProps($up['name']); @endphp
                                            <div class="flex items-center gap-1.5 border px-2 py-1 rounded {{ $props['class'] }} text-xs opacity-90 transition-colors hover:bg-opacity-50">
                                                @svg($props['icon'], 'w-3.5 h-3.5')
                                                <span class="font-mono">{{ basename($up['name']) }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                        @else
                            <div wire:ignore class="ai-markdown-content w-full overflow-x-auto custom-scrollbar font-sans" x-data="{
                                content: @js($msg['content'])
                            }" x-init="
                                const render = () => { $el.innerHTML = window.renderAiMarkdown(content); };
                                if (window.renderAiMarkdown) { render(); } else { setTimeout(render, 500); }
                            "></div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-gray-400/40 font-mono tracking-widest gap-4">
                    <x-heroicon-o-sparkles class="w-16 h-16 opacity-50" />
                    <p>Warte auf Eingabe...</p>
                </div>
            @endforelse

            <!-- Typing Indicators -->
            @foreach($typingAgents as $tId)
                @php $tAgent = $agents->firstWhere('id', $tId); @endphp
                @if($tAgent)
                <div class="flex flex-col items-start animate-fade-in-up" wire:key="typing-{{ $tId }}">
                    <div class="flex items-center gap-3 mb-1.5 ">
                        <div class="w-12 h-12 rounded shrink-0 flex justify-center items-center bg-{{ $tAgent->color }}/10 border border-{{ $tAgent->color }}/40 shadow-[0_0_10px_currentColor] text-{{ $tAgent->color }} overflow-hidden">
                            @if($tAgent->profile_picture)
                                @php
                                    $pp2 = $tAgent->profile_picture;
                                    $src2 = (str_starts_with($pp2, 'images/') || str_starts_with($pp2, 'shop/') || str_starts_with($pp2, '/'))
                                           ? asset($pp2) : Storage::url($pp2);
                                @endphp
                                <img src="{{ $src2 }}" class="w-full h-full object-cover">
                            @else
                                <x-dynamic-component :component="'heroicon-o-' . ($tAgent->icon ?: 'cpu-chip')" class="w-7 h-7" />
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
                            <span class="text-xs text-gray-500 uppercase tracking-widest animate-pulse font-bold mt-0.5">Tippe...</span>
                        </div>
                        <div wire:stream="thought_{{ $tId }}" class="mt-2 text-xs flex flex-col gap-1"></div>
                    </div>
                </div>
                @endif
            @endforeach
        </div>

        <!-- Input Area Wrapper with Drag & Drop -->
        <div class="p-4 bg-gray-950 border-t border-gray-800 z-20 shrink-0 relative"
             x-data="{ isDropping: false }"
             x-on:dragover.prevent="isDropping = true"
             x-on:dragleave.prevent="isDropping = false"
             x-on:drop.prevent="isDropping = false; $wire.uploadMultiple('uploadedFiles', $event.dataTransfer.files)">
             
            <!-- Drop Overlay -->
            <div x-show="isDropping" style="display: none;" class="absolute inset-0 bg-[var(--theme-color-10)] border-2 border-dashed border-[var(--theme-color)] z-50 flex items-center justify-center backdrop-blur-sm m-2 rounded-xl">
                <div class="text-center">
                    <x-heroicon-o-cloud-arrow-up class="w-12 h-12 text-[var(--theme-color)] mx-auto mb-2 animate-bounce" />
                    <span class="text-xl font-bold text-[var(--theme-color)] drop-shadow-md">Dateien hier ablegen</span>
                </div>
            </div>

            <div class="max-w-5xl mx-auto relative" x-data="{
                async insertMention(filePath) {
                    let inputEl = document.getElementById('aiChatInput');
                    let val = inputEl.value;
                    let pos = inputEl.selectionStart;
                    let lastAt = val.lastIndexOf('@', pos - 1);
                    if (lastAt !== -1) {
                        let newValue = val.substring(0, lastAt) + val.substring(pos);
                        $wire.set('input', newValue);
                        await $wire.addAttachment(filePath);
                        
                        // Force DOM sync for textarea resize and keep focus
                        requestAnimationFrame(() => {
                            inputEl.value = newValue; // explicitly set just in case of race condition
                            inputEl.focus();
                            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    }
                },
                checkMention() {
                    let inputEl = document.getElementById('aiChatInput');
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
                    if ($wire.mentionQuery !== '') {
                        $wire.searchFilesForMention('');
                    }
                }
            }">

                <!-- Attachment & Upload Badges -->
                @if(!empty($attachments) || !empty($uploadedFiles))
                <div class="flex flex-wrap gap-2 mb-2">
                    <!-- Project Files -->
                    @foreach($attachments as $idx => $att)
                        <div wire:key="attachment-badge-{{ md5($att . $idx) }}" class="flex items-center gap-1.5 bg-gray-900 border border-gray-700 text-gray-300 text-xs font-bold px-2.5 py-1 rounded-md shadow-sm">
                            <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                            <span class="font-mono truncate max-w-[200px]">{{ basename($att) }}</span>
                            <button type="button" wire:click="removeAttachment('{{ $idx }}')" class="hover:scale-110 transition-transform hover:text-red-400 ml-1">
                                <x-heroicon-s-x-mark class="w-4 h-4" />
                            </button>
                        </div>
                    @endforeach

                    <!-- Uploaded Local Files -->
                    @foreach($uploadedFiles as $idx => $file)
                        <div wire:key="upload-badge-{{ is_object($file) ? md5($file->getClientOriginalName() . $idx) : $idx }}" class="flex items-center gap-1.5 bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] text-xs font-bold px-2.5 py-1 rounded-md shadow-sm">
                            <x-heroicon-o-paper-clip class="w-3.5 h-3.5" />
                            <span class="font-mono truncate max-w-[200px]">{{ is_object($file) ? $file->getClientOriginalName() : 'Uploading...' }}</span>
                            <button type="button" wire:click="$removeUpload('uploadedFiles', '{{ is_object($file) ? $file->getFilename() : $idx }}')" class="hover:scale-110 transition-transform hover:text-red-400 ml-1">
                                <x-heroicon-s-x-mark class="w-4 h-4" />
                            </button>
                        </div>
                    @endforeach
                </div>
                @endif

                <!-- Mention Dropdown -->
                @if(!empty($mentionResults))
                <div class="absolute bottom-[calc(100%+0.5rem)] left-0 w-80 max-h-60 overflow-y-auto custom-scrollbar bg-gray-900 border border-[var(--theme-color-40)] rounded-xl shadow-2xl z-50">
                    <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest px-3 py-1.5 bg-gray-950 border-b border-gray-800">Projekt-Dateien anheften</div>
                    @foreach($mentionResults as $result)
                        <button type="button" @click="insertMention('{{ addslashes($result) }}')" class="w-full text-left px-3 py-2 hover:bg-[var(--theme-color-20)] transition-colors border-b border-gray-800/50 last:border-0 group">
                            <div class="flex items-center gap-2 text-sm text-gray-300 font-mono group-hover:text-[var(--theme-color)]">
                                <x-heroicon-o-document-text class="w-4 h-4 shrink-0" />
                                <span class="truncate">{{ basename($result) }}</span>
                            </div>
                            <div class="text-[10px] text-gray-500 truncate ml-6 mt-0.5">{{ dirname($result) }}</div>
                        </button>
                    @endforeach
                </div>
                @endif

                <form wire:submit.prevent="sendMessage" class="flex gap-3 relative w-full">
                    <div class="w-full relative" x-data="{ 
                        inputHeight: '56px',
                        resize() { 
                            let el = this.$refs.input;
                            el.style.height = '56px'; 
                            let finalHeight = el.scrollHeight + 'px';
                            el.style.height = finalHeight;
                            this.inputHeight = finalHeight;
                        } 
                    }">
                        <!-- Attach File Button -->
                        <div class="absolute left-2 top-1/2 -translate-y-1/2 flex items-center gap-1 z-10">
                            <label class="cursor-pointer text-gray-500 hover:text-[var(--theme-color)] transition-colors p-1" title="Datei hochladen">
                                <x-heroicon-o-paper-clip class="w-5 h-5" />
                                <input type="file" wire:model="uploadedFiles" multiple class="hidden" />
                            </label>
                        </div>

                        <textarea id="aiChatInput" x-ref="input" wire:model="input"
                               @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage() }"
                               @keyup="checkMention"
                               @click="checkMention"
                               rows="1"
                               x-init="resize()"
                               @input="resize"
                               :style="'height: ' + inputHeight"
                               @start-ai-inference.window="inputHeight = '56px'"
                               class="w-full bg-gray-950 border border-gray-800 rounded-lg pl-10 pr-16 py-4 text-[var(--theme-color)] focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color-30)] text-sm md:text-base placeholder-gray-600 transition-colors shadow-inner font-sans outline-none resize-none overflow-y-auto max-h-48 min-h-[56px] custom-scrollbar block" 
                               placeholder="Nachricht eingeben... (@Datei anheften, Shift+Enter, oder Datei einfügen)" 
                               autocomplete="off" autofocus></textarea>
                               
                        <!-- Submit Button inside the relative textarea wrapper for perfect alignment -->
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 h-10 w-12 z-10 bg-gray-800 border border-gray-700 rounded-md hover:bg-gray-700 hover:border-[var(--theme-color)] hover:text-gray-300 hover:shadow-xl shadow-[var(--theme-color-10)] text-[var(--theme-color)] flex justify-center items-center transition-all cursor-pointer">
                            <x-heroicon-s-paper-airplane class="w-6 h-6 hover:translate-x-0.5 transition-transform" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </div> <!-- CLOSES THE CHAT TAB WRAPPER -->

        <!-- PLANS TAB CONTENT -->
        <div x-show="activeTab === 'plans'" x-cloak class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-gray-900/50">
            @if(count($this->artifacts) > 0)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 h-full">
                    <!-- Sidebar: List of Plans -->
                    <div class="md:col-span-1 border-r border-gray-800 pr-4 space-y-2">
                        @foreach($this->artifacts as $idx => $art)
                            <button type="button" 
                                    wire:key="artifact-{{ md5($art['name'] ?? $idx) }}"
                                    @click="$dispatch('open-artifact', { id: {{ $idx }} })"
                                    class="w-full text-left p-3 rounded-lg border transition-all hover:border-[var(--theme-color)] {{ $loop->first ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)]' : 'bg-gray-950 border-gray-800' }}">
                                <div class="font-bold text-[var(--theme-color)] text-sm mb-1 truncate"><x-heroicon-o-document-check class="w-4 h-4 inline-block -mt-0.5" /> {{ $art['name'] }}</div>
                                <div class="text-[10px] text-gray-500 font-mono">{{ \Carbon\Carbon::createFromTimestamp($art['last_modified'])->diffForHumans() }}</div>
                            </button>
                        @endforeach
                    </div>

                    <!-- Main View: Artifact Viewer -->
                    <div class="md:col-span-3 h-full flex flex-col pt-2" 
                         x-data="{ 
                            currentArtifactId: 0,
                            artifacts: @js($this->artifacts),
                            get current() { return this.artifacts[this.currentArtifactId] || null; },
                            viewMode: 'markdown' // 'markdown' or 'code'
                         }"
                         @open-artifact.window="currentArtifactId = $event.detail.id; viewMode = 'markdown'">
                        
                        <template x-if="current">
                            <div class="flex flex-col h-full bg-gray-950 rounded-xl border border-gray-800 shadow-xl overflow-hidden">
                                <!-- Viewer Header -->
                                <div class="bg-[var(--theme-color-10)] border-b border-gray-800 px-4 py-3 flex justify-between items-center">
                                    <div class="font-mono text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase">
                                        <x-heroicon-o-document-text class="w-5 h-5 inline-block mr-2" />
                                        <span x-text="current.filename"></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="viewMode = 'markdown'" :class="viewMode === 'markdown' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">Preview</button>
                                        <button @click="viewMode = 'code'"     :class="viewMode === 'code' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">RAW Editor</button>
                                    </div>
                                </div>
                                <!-- Viewer Body -->
                                <div class="flex-1 overflow-y-auto custom-scrollbar p-6 bg-gray-900 relative">
                                    <!-- Markdown Preview -->
                                    <div x-show="viewMode === 'markdown'" 
                                         class="ai-markdown-content w-full"
                                         x-html="window.renderAiMarkdown ? window.renderAiMarkdown(current.content) : current.content">
                                    </div>

                                    <!-- RAW Code Block -->
                                    <div x-show="viewMode === 'code'" style="display: none;" class="w-full h-full">
                                        <textarea class="w-full h-full bg-gray-950 text-emerald-400 font-mono text-sm p-4 border border-gray-800 rounded outline-none custom-scrollbar" readonly x-html="current.content"></textarea>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @else
                <div class="h-full flex flex-col items-center justify-center text-gray-500 font-mono space-y-4">
                    <x-heroicon-o-document-magnifying-glass class="w-20 h-20 opacity-20" />
                    <p>Noch keine Pläne / Artefakte in diesem Projektordner generiert.</p>
                </div>
            @endif
        </div>

        <!-- FILES TAB CONTENT -->
        <div wire:key="tab-files" x-show="activeTab === 'files'" x-cloak x-data="{ zoomImage: null }" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar bg-gray-900/50 relative">
            @php
                $computedFiles = $this->globalFiles;
            @endphp
            
            <!-- LIGHTBOX OVERLAY -->
            <div x-show="zoomImage" x-cloak class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4 backdrop-blur-sm shadow-2xl">
                <div @click.outside="zoomImage = null" class="relative max-w-5xl w-full max-h-full flex justify-center shadow-[0_0_50px_black] rounded-xl">
                    <button @click="zoomImage = null" class="absolute -top-12 right-0 text-white hover:text-red-500 transition-colors drop-shadow-md">
                        <x-heroicon-o-x-mark class="w-10 h-10"/>
                    </button>
                    <img :src="zoomImage" class="max-w-full max-h-[85vh] object-contain rounded-lg border border-gray-800 shadow-[0_0_30px_rgba(255,255,255,0.05)]">
                </div>
            </div>

            @if(count($computedFiles) > 0)
                <div wire:key="files-grid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
                    @foreach($computedFiles as $file)
                        @php
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (str_ends_with(strtolower($file['name']), '.blade.php')) $ext = 'blade';
                            
                            $props = match($ext) {
                                'php' => ['icon' => 'heroicon-o-code-bracket-square', 'class' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/30'],
                                'blade' => ['icon' => 'heroicon-o-rectangle-group', 'class' => 'text-orange-400 border-orange-900/50 bg-orange-950/30'],
                                'js', 'ts', 'vue', 'json' => ['icon' => 'heroicon-o-command-line', 'class' => 'text-yellow-400 border-yellow-900/50 bg-yellow-950/30'],
                                'css', 'scss', 'html' => ['icon' => 'heroicon-o-globe-alt', 'class' => 'text-sky-400 border-sky-900/50 bg-sky-950/30'],
                                'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => ['icon' => 'heroicon-o-photo', 'class' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/30'],
                                'pdf', 'csv', 'xlsx' => ['icon' => 'heroicon-o-table-cells', 'class' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/30'],
                                default => ['icon' => 'heroicon-o-document-text', 'class' => 'text-gray-400 border-gray-700 bg-gray-900']
                            };
                        @endphp
                        <div wire:key="global-file-{{ md5($file['path'] ?? $file['name']) }}" class="border rounded-xl p-4 flex flex-col items-center justify-center text-center gap-3 transition-all hover:scale-105 hover:bg-opacity-80 shadow-md {{ $props['class'] }} relative overflow-hidden group">
                            <!-- Type Badge -->
                            <div class="absolute top-2 right-2 text-[8px] uppercase font-bold px-1.5 py-0.5 rounded opacity-50 bg-black/40 {{ $file['type'] === 'project_file' ? 'text-cyan-400' : 'text-emerald-400' }}">
                                {{ $file['type'] === 'project_file' ? 'Project' : 'Upload' }}
                            </div>
                            
                            @if(in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp']) && $file['type'] === 'local_upload')
                                <!-- Real Image Preview if Local Upload -->
                                <button type="button" @click="zoomImage = '{{ !empty($file['temporary_url']) ? $file['temporary_url'] : Storage::url($file['path']) }}'" class="cursor-pointer">
                                    <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-fuchsia-500/50 shadow-lg group-hover:border-fuchsia-400 group-hover:scale-110 group-hover:rotate-3 transition-transform relative">
                                        @if(isset($file['is_pending']))
                                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center backdrop-blur-sm z-10">
                                                <span class="text-[8px] font-bold text-white uppercase tracking-widest animate-pulse">Syncing...</span>
                                            </div>
                                        @endif
                                        <img src="{{ !empty($file['temporary_url']) ? $file['temporary_url'] : Storage::url($file['path']) }}" class="w-full h-full object-cover">
                                    </div>
                                </button>
                            @else
                                <div class="w-16 h-16 rounded-full bg-black/40 border border-current shadow-inner flex justify-center items-center">
                                    @svg($props['icon'], 'w-8 h-8 opacity-80')
                                </div>
                            @endif
                            <div class="font-mono text-xs font-bold leading-tight break-all line-clamp-2 w-full px-1" title="{{ basename($file['name']) }}">{{ basename($file['name']) }}</div>
                            <div class="text-[9px] opacity-70 mb-1 w-full truncate px-1" title="{{ dirname($file['path']) }}">{{ dirname($file['path']) === '.' ? 'Root' : dirname($file['path']) }}</div>
                            
                            <!-- Delete File Button -->
                            <div class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                @if(isset($file['is_pending']) && !empty($file['livewire_filename']))
                                    <button type="button" wire:click="$removeUpload('uploadedFiles', '{{ $file['livewire_filename'] }}')" class="bg-red-500 text-white rounded p-1 shadow-md hover:bg-red-600 hover:scale-110 transition-all" title="Upload abbrechen">
                                        <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                    </button>
                                @else
                                    <button type="button" wire:click="removeGlobalFile('{{ $file['type'] }}', '{{ addslashes($file['path']) }}')" class="bg-red-500 text-white rounded p-1 shadow-md hover:bg-red-600 hover:scale-110 transition-all" title="Aus Projekt entfernen">
                                        <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div wire:key="files-empty" class="h-full flex flex-col items-center justify-center text-gray-500 font-mono space-y-4">
                    <x-heroicon-o-folder-open class="w-20 h-20 opacity-20" />
                    <p>Noch keine Dateien in diese KI-Session eingeladen.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Hidden Custom Scrollbar Styling (Required for the webkit-scrollbar inside the custom-scrollbar class if not global) -->
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: var(--theme-color-20);
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: var(--theme-color-40);
        }
        
        .ai-markdown-content p { margin-bottom: 0.75rem; }
        .ai-markdown-content p:last-child { margin-bottom: 0; }
        .ai-markdown-content ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        .ai-markdown-content ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        .ai-markdown-content li { margin-bottom: 0.25rem; }
        /* Inline Code */
        .ai-markdown-content code:not(.hljs) { background-color: rgba(255,255,255,0.1); padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-family: monospace; font-size: 0.875em; color: var(--theme-color); }
        .ai-markdown-content blockquote { border-left: 4px solid var(--theme-color); padding-left: 1rem; color: #9ca3af; font-style: italic; margin-bottom: 0.75rem; }
        .ai-markdown-content h1, .ai-markdown-content h2, .ai-markdown-content h3 { font-weight: 700; margin-bottom: 0.75rem; margin-top: 1.5rem; color: #fff; }
        .ai-markdown-content h1 { font-size: 1.5rem; }
        .ai-markdown-content h2 { font-size: 1.25rem; }
        .ai-markdown-content h3 { font-size: 1.1rem; }
    </style>

    @push('scripts')
    <!-- Marked.js, DOMPurify, Highlight.js -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    
    <script>
        document.addEventListener('alpine:init', () => {
            // Configure marked with highlight.js
            marked.setOptions({
                highlight: function(code, lang) {
                    const language = hljs.getLanguage(lang) ? lang : 'plaintext';
                    return hljs.highlight(code, { language }).value;
                },
                langPrefix: 'hljs language-',
                breaks: true,
                gfm: true
            });

            const renderer = new marked.Renderer();
            
            renderer.code = function(...args) {
                let token = typeof args[0] === 'object' ? args[0] : null;
                let code = token ? token.text : args[0];
                let language = token ? token.lang : args[1];
                
                code = String(code || '');

                let filename = '';
                let lang = language || 'plaintext';
                if(lang.includes(':')) {
                    const parts = lang.split(':');
                    lang = parts[0];
                    filename = parts[1];
                }

                let highlightedCode = '';
                try {
                    highlightedCode = hljs.highlight(code, { language: hljs.getLanguage(lang) ? lang : 'plaintext' }).value;
                } catch(e) {
                    highlightedCode = code.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                }

                const uniqueId = 'code-' + Math.random().toString(36).substr(2, 9);
                const rawCodeEscaped = code.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

                return `
                <div class="my-5 rounded-xl overflow-hidden border border-gray-700 bg-gray-950 shadow-2xl max-w-full">
                    <div class="flex items-center justify-between px-4 py-2.5 bg-gray-900 border-b border-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                <div class="w-3 h-3 rounded-full bg-green-400"></div>
                            </div>
                            ${filename ? `<span class="ml-3 text-sm font-mono text-gray-400 font-semibold tracking-wider">${filename}</span>` : `<span class="ml-3 text-xs font-mono text-gray-500 font-bold uppercase tracking-widest">${lang}</span>`}
                        </div>
                        <button onclick="copyCodeToClipboard('${uniqueId}')" class="text-gray-400 hover:text-[var(--theme-color)] transition-colors flex items-center gap-1.5 group px-2 py-1 bg-gray-800 hover:bg-gray-700 rounded-md border border-gray-700">
                            <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            <span class="text-xs font-bold uppercase tracking-wider hidden sm:block" id="copy-text-${uniqueId}">Copy</span>
                        </button>
                    </div>
                    <div class="p-4 overflow-x-auto custom-scrollbar relative">
                        <textarea id="raw-${uniqueId}" class="hidden">${rawCodeEscaped}</textarea>
                        <pre class="!bg-transparent !m-0 !p-0"><code class="hljs language-${lang} text-[13px] font-mono leading-relaxed !bg-transparent text-gray-300">${highlightedCode}</code></pre>
                    </div>
                </div>`;
            };

            renderer.table = function(header, body) {
                // In marked v10+, the first argument is a token
                if (typeof header === 'object') {
                    const token = header;
                    const theader = marked.parse(token.header.map(cell => marked.parseInline(cell.text)).join('</th><th>'));
                    const tbody = token.rows.map(row => '<tr><td>' + row.map(cell => marked.parseInline(cell.text)).join('</td><td>') + '</td></tr>').join('');
                    return `<div class="overflow-x-auto my-4 custom-scrollbar rounded-lg border border-gray-800"><table class="w-full text-sm text-left text-gray-300"><thead class="text-xs text-gray-400 uppercase bg-gray-800/50"><tr><th>${theader}</th></tr></thead><tbody>${tbody}</tbody></table></div>`;
                }
                return `<div class="overflow-x-auto my-4 custom-scrollbar rounded-lg border border-gray-800"><table class="w-full text-sm text-left text-gray-300"><thead class="text-xs text-gray-400 uppercase bg-gray-800/50">${header}</thead><tbody>${body}</tbody></table></div>`;
            };
            renderer.tablerow = function(content) { 
                if(typeof content === 'object') return ''; // Handled in table if v13
                return `<tr class="border-b border-gray-800/50 hover:bg-gray-800/20">${content}</tr>`; 
            };
            renderer.tablecell = function(content, flags) {
                if(typeof content === 'object') return ''; // Handled in table if v13
                const type = flags && flags.header ? 'th' : 'td';
                return `<${type} class="px-4 py-3">${content}</${type}>`;
            };
            
            renderer.link = function(...args) {
                let token = typeof args[0] === 'object' ? args[0] : null;
                let href = token ? token.href : args[0];
                let title = token ? token.title : args[1];
                let text = token ? token.text : args[2];
                return `<a href="${href}" target="_blank" class="text-[var(--theme-color)] hover:underline decoration-dashed underline-offset-4 font-bold" title="${title || ''}">${text}</a>`;
            };

            window.renderAiMarkdown = function(mdContent) {
                const rawHtml = marked.parse(mdContent, { renderer: renderer });
                return DOMPurify.sanitize(rawHtml, { ADD_ATTR: ['target', 'onclick'] });
            };

            window.copyCodeToClipboard = function(id) {
                const raw = document.getElementById('raw-' + id).value;
                navigator.clipboard.writeText(raw).then(() => {
                    const textEl = document.getElementById('copy-text-' + id);
                    if(textEl) {
                        textEl.innerText = "COPIED";
                        setTimeout(() => textEl.innerText = "Copy", 2000);
                    }
                }).catch(e => console.error("Clipboard fehlgeschlagen", e));
            };
        });
    </script>
    @endpush
</div>

</div>
