<div>
    {{--
        CONTAINER POSITIONIERUNG (RESPONSIVE):

        MOBIL (Standard):
        - bottom-6 right-6: Unten rechts fixiert
        - flex-col-reverse: Fenster steht ÜBER dem Button
        - items-end: Rechtsbündig ausgerichtet

        DESKTOP (ab sm:):
        - sm:top-1/2: Vertikal mittig
        - sm:bottom-auto: "bottom" resetten
        - sm:-translate-y-1/2: Exakte Mitte
        - sm:flex-row: Fenster LINKS neben dem Button
    --}}
    <div class="fixed z-50 flex gap-4 pointer-events-none
                bottom-6 right-6 flex-col-reverse items-end
                sm:bottom-auto sm:top-1/2 sm:right-6 sm:flex-row sm:items-center sm:-translate-y-1/2">

        {{-- CHAT FENSTER --}}
        <div
            x-data="{ scrollBottom() { $refs.chatContainer.scrollTop = $refs.chatContainer.scrollHeight; } }"
            x-init="$watch('$wire.messages', () => scrollBottom())"
            {{--
                FENSTER DESIGN & ANIMATION:
                - origin-bottom-right: Mobil (wächst von unten rechts aus dem Button)
                - sm:origin-right: Desktop (wächst von rechts aus dem Button)
                - max-h-[...] statt fixer Höhe, damit es auf kleinen Handys nicht oben abgeschnitten wird
            --}}
            class="pointer-events-auto bg-white w-[90vw] sm:w-96 h-[500px] max-h-[80vh] rounded-2xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col transition-all duration-300 transform
                   origin-bottom-right sm:origin-right"
            style="{{ $isOpen ? 'opacity: 1; scale: 1;' : 'opacity: 0; scale: 0.95; pointer-events: none; visibility: hidden;' }}"
        >
            {{-- Header --}}
            <div class="bg-black p-4 flex items-center justify-between border-b border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-white/10 border border-primary/50 overflow-hidden p-1">
                            <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-contain">
                        </div>
                        <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-black rounded-full"></div>
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-sm font-serif">Funki Support</h3>
                        <p class="text-xs text-gray-400">Antwortet sofort</p>
                    </div>
                </div>
                <button wire:click="toggleChat" class="text-gray-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Messages Area --}}
            <div x-ref="chatContainer" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 custom-scrollbar">
                @foreach($messages as $msg)
                    <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        @if($msg['role'] === 'assistant')
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex-shrink-0 mr-2 flex items-center justify-center border border-primary/20 overflow-hidden p-1 self-end mb-1">
                                <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-contain">
                            </div>
                        @endif

                        <div class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm shadow-sm leading-relaxed
                        {{ $msg['role'] === 'user'
                            ? 'bg-black text-white rounded-br-none'
                            : 'bg-white text-gray-800 border border-gray-200 rounded-bl-none'
                        }}">
                            {!! nl2br(e($msg['content'])) !!}
                        </div>
                    </div>
                @endforeach

                {{-- Typing Indicator --}}
                @if($isTyping)
                    <div class="flex justify-start">
                        <div class="w-8 h-8 rounded-full bg-primary/10 flex-shrink-0 mr-2 self-end mb-1"></div>
                        <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-none px-4 py-3 shadow-sm">
                            <div class="flex space-x-1">
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Input Area --}}
            <div class="p-4 bg-white border-t border-gray-100">
                <form wire:submit.prevent="sendMessage" class="relative">
                    <input
                        wire:model="input"
                        type="text"
                        placeholder="Frag Funki etwas..."
                        class="w-full bg-gray-50 text-gray-900 placeholder-gray-400 border border-gray-200 rounded-full pl-4 pr-12 py-3 text-sm focus:ring-primary focus:border-primary focus:bg-white transition-all shadow-inner"
                        {{ $isTyping ? 'disabled' : '' }}
                    >
                    <button
                        type="submit"
                        class="absolute right-1.5 top-1.5 bg-primary hover:bg-primary-dark text-white p-2 rounded-full shadow-md transition-all transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                        {{ $isTyping || empty(trim($input)) ? 'disabled' : '' }}
                    >
                        <svg class="w-4 h-4 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </form>
                <div class="text-[10px] text-center text-gray-400 mt-2">
                    Funki ist eine KI und kann Fehler machen.
                </div>
            </div>
        </div>

        {{-- TRIGGER BUTTON (BUBBLE) --}}
        <button
            wire:click="toggleChat"
            class="pointer-events-auto group relative flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 bg-black hover:bg-gray-900 text-white rounded-full shadow-2xl transition-all duration-300 transform hover:scale-110 ring-4 ring-white/50"
        >
            @if($isOpen)
                <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            @else
                <div class="absolute inset-0 rounded-full overflow-hidden border-2 border-primary/50">
                    <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-cover">
                </div>
                {{-- Notification Dot --}}
                <span class="absolute top-1 right-1 block h-4 w-4 rounded-full ring-2 ring-white bg-red-500 animate-pulse"></span>
            @endif

            {{-- Tooltip (Nur Desktop sichtbar machen, stört mobil oft) --}}
            <span class="hidden sm:block absolute right-full mr-6 bg-white text-gray-800 text-xs font-bold px-3 py-1.5 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                Frag Funki! ✨
            </span>
        </button>

    </div>
</div>
