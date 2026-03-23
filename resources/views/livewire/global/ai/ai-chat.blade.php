<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 h-[calc(100vh-2rem)] flex flex-col"
     x-data="{
        init() {
            this.scrollToBottom();
            $watch('$wire.messages', () => { setTimeout(() => this.scrollToBottom(), 50) });
            $watch('$wire.typingAgents', () => { setTimeout(() => this.scrollToBottom(), 50) });
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
    <div class="mb-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0">
        <div>
            <h2 class="text-3xl font-bold text-emerald-500 mb-1 font-mono tracking-tight shadow-emerald-500/20 drop-shadow-md">
                KI Chat Konsole
            </h2>
            <p class="text-emerald-700/80 font-mono text-xs uppercase tracking-widest">Multi-Agenten System Interaktives Terminal</p>
        </div>
    </div>

    <!-- Fließende Agenten Zeile -->
    <div class="bg-black/60 border border-emerald-900/30 rounded-xl p-4 mb-6 shrink-0 shadow-[0_0_30px_rgba(16,185,129,0.05)] overflow-hidden relative backdrop-blur-md">
        <div class="absolute left-0 top-0 bottom-0 w-12 bg-gradient-to-r from-black via-black/80 to-transparent z-10 pointer-events-none"></div>
        <div class="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-l from-black via-black/80 to-transparent z-10 pointer-events-none"></div>

        <div class="text-[10px] text-emerald-600/60 font-mono uppercase tracking-widest mb-3 pl-2">Verfügbare Agenten (Klicken zum Verbinden):</div>
        <div class="flex items-center gap-4 overflow-x-auto custom-scrollbar pb-3 pt-1 px-4 snap-x">
            @foreach($agents as $agent)
                @php
                    $isActive = in_array($agent->id, $activeAgentIds);
                @endphp
                <button wire:click="toggleAgent('{{ $agent->id }}')"
                        class="shrink-0 snap-start flex items-center gap-3 px-4 py-2.5 rounded-lg border transition-all duration-300 {{ $isActive ? 'bg-emerald-950/40 border-emerald-500/50 shadow-[0_0_20px_rgba(16,185,129,0.1)] scale-105' : 'bg-black/80 border-gray-800 hover:border-emerald-900/50 hover:bg-emerald-950/20' }}">

                    <div class="relative">
                        <div class="w-10 h-10 rounded bg-{{ $agent->color }}/10 flex items-center justify-center border border-{{ $agent->color }}/30 text-{{ $agent->color }} {{ $isActive ? 'shadow-[0_0_12px_currentColor]' : 'opacity-40' }} transition-all overflow-hidden relative">
                            @if($agent->profile_picture)
                                <img src="{{ Storage::url($agent->profile_picture) }}" alt="{{ $agent->name }}" class="w-full h-full object-cover">
                            @else
                                @if(str_starts_with($agent->icon, 'bi-'))
                                    <i class="{{ $agent->icon }} text-xl drop-shadow-[0_0_5px_currentColor]"></i>
                                @elseif(str_starts_with(trim($agent->icon), '<svg'))
                                    <div class="w-6 h-6 [&>svg]:w-full [&>svg]:h-full drop-shadow-[0_0_5px_currentColor]">{!! $agent->icon !!}</div>
                                @else
                                    <x-dynamic-component :component="'heroicon-o-' . ($agent->icon ?: 'cpu-chip')" class="w-6 h-6" />
                                @endif
                            @endif
                        </div>
                        <!-- Status Dot -->
                        <span class="absolute -bottom-1 -right-1 flex h-3.5 w-3.5">
                            @if($isActive)
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-black"></span>
                            @else
                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-gray-600 border-2 border-black"></span>
                            @endif
                        </span>
                    </div>

                    <div class="text-left min-w-[100px]">
                        <div class="text-sm font-bold font-mono tracking-wider {{ $isActive ? 'text-'.$agent->color : 'text-gray-500' }} drop-shadow-md">{{ $agent->name }}</div>
                        <div class="text-[9px] font-mono uppercase text-gray-500 tracking-widest mt-0.5 flex items-center gap-1">
                            @if($isActive)
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                                Verbunden
                            @else
                                <div class="w-1.5 h-1.5 rounded-full bg-gray-600"></div>
                                Offline
                            @endif
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    <!-- Matrix Chat Terminal -->
    <div class="flex-1 bg-black/95 border border-emerald-900/50 rounded-xl shadow-[0_0_40px_rgba(16,185,129,0.1)] flex flex-col overflow-hidden backdrop-blur-xl">
        <!-- Terminal Header -->
        <div class="bg-emerald-950/40 border-b border-emerald-900/50 p-3 flex justify-between items-center relative overflow-hidden shrink-0">
            <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/10 to-transparent"></div>
            <div class="flex items-center gap-3 relative z-10">
                <x-heroicon-o-command-line class="w-6 h-6 text-emerald-500 animate-pulse" />
                <div>
                    <span class="text-emerald-500 font-bold text-sm tracking-widest uppercase shadow-emerald-500 block">Verschlüsselte Übertragung</span>
                    <span class="text-emerald-700 text-[10px] font-mono uppercase tracking-widest">Aktive Agenten: {{ count($activeAgentIds) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-3 relative z-10 text-emerald-700">
                <a href="/admin/ai-logs" title="System Logs" class="hover:text-emerald-400 transition-colors">
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
                                    $src = (str_starts_with($pp, 'images/') || str_starts_with($pp, '/'))
                                           ? asset($pp) : Storage::url($pp);
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
                                    $src2 = (str_starts_with($pp2, 'images/') || str_starts_with($pp2, '/'))
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
                        <span class="text-xs text-emerald-600/70 uppercase tracking-widest animate-pulse font-bold mt-0.5">Berechne...</span>
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
            <div class="mt-2 text-center text-[10px] text-emerald-800/60 font-mono tracking-widest uppercase flex justify-center items-center gap-4">
                <span><i class="text-emerald-500/50">Last:</i> {{ sys_getloadavg()[0] ?? '?' }}</span>
                <span><i class="text-emerald-500/50">Speicher:</i> {{ count($messages) }}</span>
                <span><i class="text-emerald-500/50">Krypto:</i> AES-256-GCM / sicher</span>
            </div>
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
