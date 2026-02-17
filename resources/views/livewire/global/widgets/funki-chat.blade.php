<div class="relative font-sans">
    <div class="fixed z-[9999] flex gap-4 pointer-events-none bottom-6 right-6 flex-col items-end">

        {{-- HAUPTFENSTER --}}
        <div x-data="{ isMaximized: false }"
             class="pointer-events-auto bg-white rounded-[2.5rem] shadow-[0_20px_60px_rgba(0,0,0,0.15)] border border-slate-100 overflow-hidden flex flex-col transition-all duration-500 transform origin-bottom-right mb-4"
             :class="isMaximized
                ? 'w-[calc(100vw-2rem)] h-[85vh] sm:w-[800px] sm:h-[85vh]'
                : 'w-[calc(100vw-3rem)] h-[60vh] sm:w-[450px] sm:h-[700px]'"
             x-show="$wire.isOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-10"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-cloak>

            {{-- Header --}}
            <div class="bg-slate-900 p-6 flex items-center justify-between relative overflow-hidden shrink-0">
                <div class="absolute inset-0 bg-gradient-to-tr from-blue-600/30 to-transparent animate-pulse"></div>

                <div class="flex items-center gap-4 relative z-10">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 p-1 backdrop-blur-xl border border-white/20">
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-cover rounded-xl shadow-sm">
                    </div>
                    <div>
                        <h3 class="text-white font-black text-sm tracking-widest uppercase italic">Funki Chat</h3>
                        <div class="flex items-center gap-1.5 text-green-400">
                            <span class="w-2 h-2 bg-current rounded-full animate-pulse shadow-[0_0_8px_currentColor]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-wider">Bereit</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1 relative z-10">
                    <button @click="isMaximized = !isMaximized" class="text-slate-400 hover:text-white w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10 transition-all">
                        <svg x-show="!isMaximized" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                        <svg x-show="isMaximized" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5M15 15l5.25 5.25" />
                        </svg>
                    </button>
                    <button wire:click="toggleChat" class="text-slate-400 hover:text-red-400 w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10 transition-all transform hover:rotate-90">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mode Switcher --}}
            <div class="flex bg-slate-50 p-2 gap-2 border-b border-slate-100 shrink-0">
                <button wire:click="setMode('chat')" class="flex-1 py-3 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all {{ $activeMode === 'chat' ? 'bg-white shadow-sm text-blue-600 border border-blue-100' : 'text-slate-400 hover:bg-slate-100' }}">
                    Chat
                </button>
                @if(auth()->guard('admin')->check())
                    <button wire:click="setMode('logs')" class="flex-1 py-3 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all {{ $activeMode === 'logs' ? 'bg-white shadow-sm text-blue-600 border border-blue-100' : 'text-slate-400 hover:bg-slate-100' }}">
                        System Log
                    </button>
                @endif
            </div>

            <div class="flex-1 overflow-hidden flex flex-col bg-white">
                @if($activeMode === 'chat')
                    <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/20 custom-scrollbar">
                        @foreach($messages as $msg)
                            <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[85%] rounded-[1.8rem] px-5 py-3.5 text-sm shadow-sm {{ $msg['role'] === 'user' ? 'bg-slate-900 text-white rounded-br-none' : 'bg-white text-slate-700 border border-slate-100 rounded-bl-none font-medium' }}">
                                    {!! nl2br(e($msg['content'])) !!}
                                </div>
                            </div>
                        @endforeach
                        @if($isTyping)
                            <div class="flex justify-start animate-pulse">
                                <div class="bg-slate-100 rounded-2xl px-5 py-3 text-slate-400 text-xs font-bold uppercase tracking-widest">Funki schreibt...</div>
                            </div>
                        @endif
                    </div>
                    <div class="p-5 bg-white border-t border-slate-50 shrink-0">
                        <form wire:submit.prevent="sendMessage" class="relative flex items-center bg-slate-100 rounded-[1.8rem] p-1.5 shadow-inner transition-all group focus-within:bg-white focus-within:ring-2 focus-within:ring-primary/10">
                            <input wire:model="input" type="text" placeholder="Frag Funki etwas..." class="flex-1 bg-transparent border-0 pl-5 py-4 text-sm focus:ring-0 outline-none">

                            <button type="submit" class="bg-primary text-white w-12 h-12 rounded-full shadow-lg hover:bg-primary-dark active:scale-95 transition-all flex items-center justify-center group/send">
                                {{-- Heroicon: paper-airplane (solid) --}}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 transition-transform group-hover/send:translate-x-0.5 group-hover/send:-translate-y-0.5">
                                    <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @elseif($activeMode === 'logs')
                    <div class="flex-1 overflow-y-auto p-0 bg-slate-50/30 custom-scrollbar">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead class="sticky top-0 bg-white shadow-sm z-10">
                            <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <th class="px-5 py-3">Aktivität</th>
                                <th class="px-5 py-3 text-right">Status</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                            @forelse($this->history as $entry)
                                <tr class="hover:bg-white transition-colors">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            @php
                                                $icon = match($entry->status) {
                                                    'success' => 'bi-check-circle-fill text-green-500',
                                                    'error' => 'bi-exclamation-triangle-fill text-red-500',
                                                    'running' => 'bi-arrow-repeat text-blue-500 animate-spin',
                                                    default => 'bi-info-circle-fill text-slate-400'
                                                };
                                            @endphp
                                            <i class="bi {{ $icon }} fs-6"></i>
                                            <div>
                                                <span class="block font-black text-slate-900 leading-none mb-1">{{ $entry->title }}</span>
                                                <span class="text-[9px] text-slate-400 font-mono">{{ $entry->started_at->format('H:i:s') }} — {{ $entry->started_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                            <span class="text-[9px] font-black uppercase tracking-widest {{ $entry->status === 'success' ? 'text-green-600' : 'text-red-500' }}">
                                                {{ $entry->status }}
                                            </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="p-10 text-center text-slate-400 italic">Noch keine Logs vorhanden.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- TRIGGER BUTTON --}}
        <button wire:click="toggleChat" class="pointer-events-auto relative group outline-none shrink-0 transition-all active:scale-90">
            <div class="absolute inset-0 bg-blue-600 rounded-full blur-2xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-900 rounded-[1.8rem] p-1 shadow-2xl transition-all duration-500 transform group-hover:scale-110 group-hover:-rotate-3 flex items-center justify-center border-2 border-white relative z-10 overflow-hidden shrink-0">
                <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-cover rounded-[1.4rem] shadow-inner">
                <div x-show="!$wire.isOpen" x-transition.opacity class="absolute top-1.5 right-1.5 flex h-6 w-6">
                    <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative h-6 w-6 bg-blue-600 border-2 border-white rounded-full flex items-center justify-center text-[8px] font-black text-white shadow-sm italic">
                        OS
                    </span>
                </div>
            </div>
        </button>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</div>
