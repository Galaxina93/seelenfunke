<div class="relative font-sans">
    <div class="fixed z-[9999] flex gap-4 pointer-events-none bottom-6 right-6 flex-col-reverse items-end sm:flex-row sm:items-center">

        {{-- HAUPTFENSTER --}}
        <div class="pointer-events-auto bg-white w-[95vw] sm:w-[500px] h-[780px] max-h-[88vh] rounded-[2.5rem] shadow-[0_20px_60px_rgba(0,0,0,0.15)] border border-slate-100 overflow-hidden flex flex-col transition-all duration-500 transform origin-bottom-right sm:origin-right shadow-blue-900/5"
             x-show="$wire.isOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-10"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-cloak>

            {{-- Modern Header --}}
            <div class="bg-slate-900 p-6 flex items-center justify-between relative overflow-hidden shrink-0">
                <div class="absolute inset-0 bg-gradient-to-tr from-blue-600/30 to-transparent animate-pulse"></div>
                <div class="flex items-center gap-4 relative z-10">
                    <div class="w-14 h-14 rounded-2xl bg-white/10 p-1 backdrop-blur-xl border border-white/20 shadow-lg">
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-cover rounded-xl shadow-sm">
                    </div>
                    <div>
                        <h3 class="text-white font-black text-sm tracking-widest uppercase italic">Funki OS</h3>
                        <div class="flex items-center gap-1.5 text-green-400">
                            <span class="w-2 h-2 bg-current rounded-full animate-pulse shadow-[0_0_8px_currentColor]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-wider">Aktiv</p>
                        </div>
                    </div>
                </div>
                <button wire:click="toggleChat" class="text-slate-400 hover:text-white p-2 transition-all transform hover:rotate-90">
                    <i class="bi bi-x-lg fs-4"></i>
                </button>
            </div>

            {{-- Mode Switcher (Nur Admin sieht Automations) --}}
            <div class="flex bg-slate-50 p-2 gap-2 border-b border-slate-100 shrink-0">
                <button wire:click="setMode('chat')" class="flex-1 py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all {{ $activeMode === 'chat' ? 'bg-white shadow-sm text-blue-600 border border-blue-100' : 'text-slate-400 hover:bg-slate-100' }}">
                    <i class="bi bi-chat-heart-fill me-1.5"></i> Kommunikation
                </button>
                @if(auth()->guard('admin')->check())
                    <button wire:click="setMode('automations')" class="flex-1 py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all {{ $activeMode === 'automations' ? 'bg-white shadow-sm text-blue-600 border border-blue-100' : 'text-slate-400 hover:bg-slate-100' }}">
                        <i class="bi bi-cpu-fill me-1.5"></i> Automatisierungen
                    </button>
                @endif
            </div>

            <div class="flex-1 overflow-hidden flex flex-col bg-white">
                @if($activeMode === 'chat')
                    {{-- CHAT MODUS --}}
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
                                <div class="bg-slate-100 rounded-2xl px-5 py-3 text-slate-400 text-xs font-bold uppercase tracking-widest">Funki denkt nach...</div>
                            </div>
                        @endif
                    </div>
                    {{-- Eingabeleiste --}}
                    <div class="p-5 bg-white border-t border-slate-50 shrink-0">
                        <form wire:submit.prevent="sendMessage" class="relative flex items-center bg-slate-100 rounded-[1.8rem] p-1.5 shadow-inner focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100 transition-all group">
                            <input wire:model="input" type="text" placeholder="Befehl an Funki..." class="flex-1 bg-transparent border-0 pl-5 py-4 text-sm focus:ring-0 outline-none placeholder-slate-400">
                            <button type="submit" class="bg-blue-600 text-white w-14 h-14 rounded-full shadow-lg hover:bg-blue-700 active:scale-95 transition-all flex items-center justify-center">
                                <i class="bi bi-send-fill text-xl"></i>
                            </button>
                        </form>
                    </div>
                @elseif($activeMode === 'automations')
                    {{-- AUTOMATISIERUNGEN --}}
                    <div class="flex-1 overflow-y-auto p-6 space-y-8 bg-slate-50/30 custom-scrollbar text-left">
                        <div class="relative bg-white border border-slate-100 rounded-[2rem] p-6 shadow-sm">
                            <div class="flex items-center gap-5">
                                <div class="relative shrink-0">
                                    <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-16 h-16 rounded-full border-4 border-blue-50">
                                    <span class="absolute -top-1 -right-1 bg-blue-600 text-white text-[8px] font-black px-1.5 py-0.5 rounded-full shadow-sm uppercase">CEO</span>
                                </div>
                                <div>
                                    <h4 class="text-base font-black text-slate-900">Funki Operations</h4>
                                    <p class="text-xs text-slate-500 leading-relaxed italic">"Ich kümmere mich um den Rest, Alina."</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-3 px-2">
                                <span class="w-8 h-px bg-slate-200"></span> Aktive Prozesse
                            </h4>
                            @foreach($autoTasks as $task)
                                <div class="bg-white rounded-3xl border border-slate-100 p-5 shadow-sm hover:border-blue-200 transition-all group relative overflow-hidden text-left">
                                    @if($task['status'] === 'active')
                                        <div class="absolute top-0 right-0 p-2">
                                            <span class="flex h-2 w-2 rounded-full bg-green-500 animate-pulse shadow-[0_0_10px_#22c55e]"></span>
                                        </div>
                                    @endif
                                    <div class="flex items-start gap-5">
                                        <div class="w-12 h-12 shrink-0 rounded-2xl {{ $task['status'] === 'active' ? 'bg-blue-50 text-blue-600' : 'bg-slate-50 text-slate-300' }} flex items-center justify-center transition-transform group-hover:scale-110">
                                            <i class="bi {{ $task['icon'] }} fs-4"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h5 class="text-sm font-black text-slate-900">{{ $task['name'] }}</h5>
                                            <p class="text-[10px] text-slate-500 mt-1 leading-normal">{{ $task['description'] }}</p>
                                            <div class="flex items-center gap-4 mt-3">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter flex items-center gap-1.5">
                                                    <i class="bi bi-clock-history"></i> {{ $task['schedule'] }}
                                                </span>
                                                <span class="text-[9px] font-black text-blue-500 uppercase tracking-tighter flex items-center gap-1.5">
                                                    <i class="bi bi-calendar-check"></i> {{ $task['last_run'] }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-4 pb-4 px-2">
                            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-3">
                                <span class="w-8 h-px bg-slate-200"></span> Funki Log-Historie
                            </h4>
                            <div class="bg-white border border-slate-100 rounded-[1.8rem] overflow-hidden shadow-sm">
                                <table class="w-full text-xs text-left border-collapse">
                                    <tbody class="divide-y divide-slate-50">
                                    @forelse($this->history as $entry)
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-5 py-4">
                                                <div class="flex items-center gap-3 text-left">
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
                                                        <span class="block font-black text-slate-900 leading-none mb-1 text-left">{{ $entry->title }}</span>
                                                        <span class="text-[9px] text-slate-400 font-mono text-left">{{ $entry->started_at->format('H:i:s') }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <span class="text-[9px] font-black uppercase tracking-widest {{ $entry->status === 'success' ? 'text-green-600' : 'text-red-500' }}">
                                                    {{ $entry->status === 'success' ? 'Erfolg' : ($entry->status === 'running' ? 'Läuft' : 'Fehler') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td class="p-10 text-center text-slate-400 italic font-medium">Funkis Tagebuch ist noch leer...</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- TRIGGER BUTTON --}}
        <button wire:click="toggleChat" class="pointer-events-auto relative group outline-none shrink-0 transition-all active:scale-90">
            <div class="absolute inset-0 bg-blue-600 rounded-full blur-2xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-900 rounded-[1.8rem] p-1 shadow-2xl transition-all duration-500 transform group-hover:scale-110 group-hover:-rotate-3 flex items-center justify-center border-2 border-white relative z-10 overflow-hidden shrink-0">
                <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-cover rounded-[1.4rem] shadow-inner" style="max-width: 100%; max-height: 100%;">

                <div x-show="!$wire.isOpen" x-transition.opacity class="absolute top-1.5 right-1.5 flex h-6 w-6">
                    <span class="animate-ping absolute inset-0 rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative h-6 w-6 bg-blue-600 border-2 border-white rounded-full flex items-center justify-center text-[8px] font-black text-white shadow-sm italic font-serif">
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
        .no-scrollbar::-webkit-scrollbar { display: none; }
        @keyframes spin-slow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .animate-spin-slow { animation: spin-slow 12s linear infinite; }
    </style>
</div>
