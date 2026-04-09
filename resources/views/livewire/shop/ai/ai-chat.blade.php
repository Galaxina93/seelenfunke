<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 flex flex-col transition-all duration-300 relative"
     :class="isFullscreen ? 'fixed inset-0 z-[100] !h-[100dvh] !w-full !max-w-none !p-0 !m-0 bg-black/95 backdrop-blur-3xl' : 'h-[calc(100vh-2rem)]'"
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
    <div class="mb-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0 transition-all duration-300"
         :class="isFullscreen ? 'hidden' : 'px-4 sm:px-0'">
        <div>
            <h2 class="text-3xl font-bold text-emerald-500 mb-1 font-mono tracking-tight shadow-emerald-500/20 drop-shadow-md">
                KI Chat Konsole
            </h2>
            <p class="text-emerald-700/80 font-mono text-xs uppercase tracking-widest">Multi-Agenten System Interaktives Terminal</p>
        </div>
    </div>

    <!-- Matrix Chat Terminal -->
    <div class="flex-1 bg-black/95 border border-emerald-900/50 rounded-xl shadow-[0_0_40px_rgba(16,185,129,0.1)] flex flex-col overflow-hidden backdrop-blur-xl transition-all duration-300"
         :class="isFullscreen ? '!border-0 !rounded-none' : ''">
        <!-- Terminal Header -->
        <div class="bg-emerald-950/40 border-b border-emerald-900/50 p-3 flex flex-col md:flex-row justify-between md:items-center gap-3 relative overflow-visible shrink-0">
            <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/10 to-transparent pointer-events-none"></div>

            <div class="flex justify-between w-full md:w-auto items-center gap-4 relative z-10 shrink-0">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-command-line class="w-6 h-6 text-emerald-500 animate-pulse hidden sm:block" />
                    <div>
                        <span class="text-emerald-500 font-bold text-xs sm:text-sm tracking-widest uppercase shadow-emerald-500 block leading-tight">Verschlüsselte Übertragung</span>
                        <span class="text-emerald-700 text-[10px] font-mono uppercase tracking-widest">Verfügbare Agenten</span>
                    </div>
                </div>

                <!-- Mobile Actions Top Right -->
                <div class="flex md:hidden items-center gap-3 text-emerald-700">
                    <button @click="isFullscreen = !isFullscreen" title="Vollbild" class="hover:text-emerald-400 transition-colors">
                        <x-heroicon-s-arrows-pointing-out class="w-5 h-5 drop-shadow-md" x-show="!isFullscreen" />
                        <x-heroicon-s-arrows-pointing-in class="w-5 h-5 drop-shadow-md" x-show="isFullscreen" style="display: none;" />
                    </button>
                    <button wire:click="clearChat" wire:confirm="Sicher, dass du den Chat-Verlauf restlos wipen möchtest?" class="text-red-900/60 hover:text-red-500 transition-colors">
                        <x-heroicon-o-trash class="w-5 h-5 drop-shadow-md" />
                    </button>
                    <button @click="$dispatch('open-profile-modal', {tab: '2fa'})" class="hover:text-emerald-400">
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
                                class="shrink-0 relative group rounded-full border transition-all duration-300 flex items-center gap-2 pr-4 bg-black/50 {{ $isActive ? 'border-emerald-500/80 shadow-[0_0_10px_rgba(16,185,129,0.2)] bg-emerald-950/40' : 'border-emerald-900/50 hover:border-emerald-500/30 opacity-70 hover:opacity-100' }}">

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
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 border border-black"></span>
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
            <div class="hidden md:flex items-center justify-end gap-3 relative z-10 text-emerald-700 shrink-0">
                <button @click="isFullscreen = !isFullscreen" title="Vollbild" class="hover:text-emerald-400 transition-colors">
                    <x-heroicon-s-arrows-pointing-out class="w-5 h-5 drop-shadow-md" x-show="!isFullscreen" />
                    <x-heroicon-s-arrows-pointing-in class="w-5 h-5 drop-shadow-md" x-show="isFullscreen" style="display: none;" />
                </button>
                <a href="/admin/global-logs" title="System Logs" class="hover:text-emerald-400 transition-colors">
                    <x-heroicon-s-server-stack class="w-5 h-5 drop-shadow-md" />
                </a>
                <button @click="$dispatch('open-profile-modal', {tab: '2fa'})" title="Sicherheits-Firewall" class="hover:text-emerald-400 transition-colors">
                    <x-heroicon-s-shield-check class="w-5 h-5 drop-shadow-md" />
                </button>
                <div class="w-px h-5 bg-emerald-900/50 mx-2"></div>
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
                        <div class="w-12 h-12 rounded shrink-0 flex justify-center items-center bg-{{ $msg['color'] ?: 'emerald-500' }}/10 border border-{{ $msg['color'] ?: 'emerald-500' }}/40 shadow-[0_0_10px_currentColor] text-{{ $msg['color'] ?: 'emerald-500' }} overflow-hidden">
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
                        <span class="text-xs font-bold text-{{ $msg['color'] ?: 'emerald-500' }} tracking-widest uppercase truncate max-w-[200px]">{{ $msg['name'] }}</span>
                    </div>
                    <div class="max-w-[85%] font-mono text-base leading-relaxed p-4 rounded-xl {{ $msg['role'] === 'user' ? 'bg-black border border-gray-700 text-gray-300 rounded-tr-none shadow-md' : 'bg-emerald-950/20 text-emerald-50/90 rounded-tl-none border border-emerald-900/60 shadow-[0_0_20px_rgba(16,185,129,0.05)]' }}">
                        {!! nl2br(e($msg['content'])) !!}
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-emerald-700/40 font-mono tracking-widest gap-4">
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
                    <div class="max-w-[85%] font-mono text-sm leading-relaxed px-5 py-3 rounded-xl bg-emerald-950/20 text-emerald-50/90 rounded-tl-none border border-emerald-900/60 shadow-[0_0_20px_rgba(16,185,129,0.05)] flex items-center gap-3">
                        <span class="flex gap-1.5 pt-1">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-bounce"></span>
                        </span>
                        <span class="text-xs text-emerald-600/70 uppercase tracking-widest animate-pulse font-bold mt-0.5">Tippe...</span>
                    </div>
                </div>
                @endif
            @endforeach
        </div>

        <!-- Input Area -->
        <div class="p-4 bg-black border-t border-emerald-900/60 z-20 shrink-0">
            <form wire:submit.prevent="sendMessage" class="flex gap-3 relative max-w-5xl mx-auto">
                <input wire:model="input" type="text" class="w-full bg-gray-950 border border-emerald-900/50 rounded-lg pl-6 pr-16 py-4 text-emerald-400 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-500/30 text-sm md:text-base placeholder-emerald-900/60 transition-all shadow-inner font-mono outline-none" placeholder="Nachricht eingeben..." autocomplete="off" autofocus>

                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 h-10 w-12 bg-emerald-900/30 border border-emerald-800 rounded-md hover:bg-emerald-800 hover:border-emerald-400 hover:text-emerald-300 hover:shadow-[0_0_15px_rgba(16,185,129,0.3)] text-emerald-500 flex justify-center items-center transition-all cursor-pointer">
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
            background: rgba(16, 185, 129, 0.2);
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.4);
        }
    </style>
</div>
