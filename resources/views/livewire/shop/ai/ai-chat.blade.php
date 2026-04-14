<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 flex flex-col transition-all duration-300 relative"
     :class="isFullscreen ? 'fixed inset-0 z-[100] !h-[100dvh] !w-full !max-w-none !p-0 !m-0 bg-gray-950/95 backdrop-blur-3xl' : 'h-[calc(100vh-2rem)]'"
     x-data="{
        isFullscreen: false,
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

        <!-- Messages Area -->
        <div id="chat-scroll-container" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar scroll-smooth">
            @forelse($messages as $msg)
                <div class="flex flex-col {{ $msg['role'] === 'user' ? 'items-end' : 'items-start' }} animate-fade-in-up">
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
                    <div class="max-w-[85%] font-mono text-base leading-relaxed p-4 rounded-xl {{ $msg['role'] === 'user' ? 'bg-gray-950 border border-gray-700 text-gray-300 rounded-tr-none shadow-md' : 'bg-[var(--theme-color-10)] text-gray-200 rounded-tl-none border border-gray-800 shadow-xl shadow-[var(--theme-color-10)]' }}">
                        {!! nl2br(e($msg['content'])) !!}
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
                    <div class="max-w-[85%] font-mono text-sm leading-relaxed px-5 py-3 rounded-xl bg-[var(--theme-color-10)] text-gray-200 rounded-tl-none border border-gray-800 shadow-xl shadow-[var(--theme-color-10)] flex items-center gap-3">
                        <span class="flex gap-1.5 pt-1">
                            <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                            <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                            <span class="w-1.5 h-1.5 bg-[var(--theme-color)] rounded-full animate-bounce"></span>
                        </span>
                        <span class="text-xs text-gray-500 uppercase tracking-widest animate-pulse font-bold mt-0.5">Tippe...</span>
                    </div>
                </div>
                @endif
            @endforeach
        </div>

        <!-- Input Area -->
        <div class="p-4 bg-gray-950 border-t border-gray-800 z-20 shrink-0">
            <form wire:submit.prevent="sendMessage" class="flex gap-3 relative max-w-5xl mx-auto">
                <input wire:model="input" type="text" class="w-full bg-gray-950 border border-gray-800 rounded-lg pl-6 pr-16 py-4 text-[var(--theme-color)] focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color-30)] text-sm md:text-base placeholder-gray-600 transition-all shadow-inner font-sans outline-none" placeholder="Nachricht eingeben..." autocomplete="off" autofocus>

                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 h-10 w-12 bg-gray-800 border border-gray-700 rounded-md hover:bg-gray-700 hover:border-[var(--theme-color)] hover:text-gray-300 hover:shadow-xl shadow-[var(--theme-color-10)] text-[var(--theme-color)] flex justify-center items-center transition-all cursor-pointer">
                    <x-heroicon-s-paper-airplane class="w-6 h-6 hover:translate-x-0.5 transition-transform" />
                </button>
            </form>
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
    </style>
</div>

</div>
