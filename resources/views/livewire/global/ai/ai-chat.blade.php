<div class="fixed bottom-20 right-4 z-50 flex flex-col items-end">
    @if($isOpen)
    <div class="w-80 sm:w-96 h-[32rem] mb-4 bg-black/95 border border-emerald-900/60 rounded-xl backdrop-blur-xl shadow-[0_0_40px_rgba(16,185,129,0.15)] flex flex-col overflow-hidden font-mono text-sm">
        
        <!-- Header -->
        <div class="bg-emerald-950/40 border-b border-emerald-900/50 p-3 flex justify-between items-center relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/10 to-transparent"></div>
            <div class="flex items-center gap-2 relative z-10">
                <i class="bi bi-terminal text-emerald-500 animate-pulse"></i>
                <span class="text-emerald-500 font-bold text-xs tracking-widest uppercase shadow-emerald-500">MAS Terminal</span>
            </div>
            <button wire:click="toggle" class="text-emerald-700 hover:text-emerald-400 relative z-10 transition-colors cursor-pointer">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Messages Area -->
        <div class="flex-1 overflow-y-auto p-4 space-y-5 custom-scrollbar">
            @foreach($messages as $msg)
                <div class="flex flex-col {{ $msg['role'] === 'user' ? 'items-end' : 'items-start' }} animate-fade-in-up">
                    <div class="flex items-center gap-2 mb-1 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                        <div class="w-6 h-6 rounded flex justify-center items-center bg-{{ $msg['color'] ?: 'emerald-500' }}/10 border border-{{ $msg['color'] ?: 'emerald-500' }}/30 shadow-[0_0_8px_currentColor] text-{{ $msg['color'] ?: 'emerald-500' }}">
                            <i class="{{ $msg['icon'] ?: 'bi-robot' }} text-[10px]"></i>
                        </div>
                        <span class="text-[10px] font-bold text-{{ $msg['color'] ?: 'emerald-500' }} tracking-wider uppercase">{{ $msg['name'] }}</span>
                    </div>
                    <div class="max-w-[85%] text-xs leading-relaxed p-3 rounded-lg {{ $msg['role'] === 'user' ? 'bg-gray-800 text-gray-200 rounded-tr-none border border-gray-700' : 'bg-emerald-950/20 text-emerald-100/90 rounded-tl-none border border-emerald-900/60 shadow-[0_0_15px_rgba(16,185,129,0.05)]' }}">
                        {!! nl2br(e($msg['content'])) !!}
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Input Area -->
        <div class="p-3 bg-black border-t border-emerald-900/60 z-20">
            <form wire:submit.prevent="sendMessage" class="flex gap-2 relative">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-700 font-bold">></div>
                <input wire:model.defer="input" type="text" class="w-full bg-gray-950 border border-emerald-900/50 rounded-lg pl-8 pr-12 py-3 text-emerald-400 focus:border-emerald-500 focus:ring focus:ring-emerald-500/20 text-xs placeholder-emerald-900/60 focus:outline-none transition-all shadow-inner" placeholder="Execute command...">
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-emerald-900/30 border border-emerald-800 rounded hover:bg-emerald-800 hover:border-emerald-500 hover:text-emerald-300 text-emerald-600 flex justify-center items-center transition-all cursor-pointer">
                    <i class="bi bi-send-fill text-xs"></i>
                </button>
            </form>
        </div>
    </div>
    @endif
</div>
