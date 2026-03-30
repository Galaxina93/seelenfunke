<div class="flex flex-col h-full bg-gray-50/50" x-data="{
        scrollToBottom() {
            setTimeout(() => {
                const el = this.$refs.chatContainer;
                if(el) { el.scrollTop = el.scrollHeight; }
            }, 100);
        }
    }"
    @message-sent.window="scrollToBottom"
    @message-received.window="scrollToBottom"
    x-init="scrollToBottom"
>
    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-cyan-600 to-cyan-500 rounded-t-3xl shadow-md z-10 shrink-0">
        <div class="flex items-center space-x-3">
            <div class="relative">
                <img src="{{ $agentImage ?: asset('shop/ai/images/funki_selfie.png') }}" alt="{{ $agentName }}" class="w-10 h-10 rounded-full border-2 border-white/30 object-cover shadow-inner">
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-cyan-500 rounded-full"></span>
            </div>
            <div>
                <h3 class="text-white font-bold text-sm tracking-wide">{{ $agentName }}</h3>
                <p class="text-cyan-100 text-xs font-medium">Support & Beratung</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            {{-- Maximize Button --}}
            <button @click="chatMaximized = !chatMaximized" class="text-white/70 hover:text-white transition-colors bg-white/10 hover:bg-white/20 p-2 rounded-full" title="Fenster vergrößern/verkleinern">
                <svg x-show="!chatMaximized" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                <svg x-show="chatMaximized" style="display:none;" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                </svg>
            </button>
            
            {{-- Close Button --}}
            <button @click="if({{ count($messages) }} > 0 && !'{{ $isResolved }}') { if(confirm('Möchtest du den Support-Chat wirklich schließen? Deine Sitzung ist noch aktiv.')) { chatOpen = false; setTimeout(() => chatMaximized = false, 400); } } else { chatOpen = false; setTimeout(() => chatMaximized = false, 400); }" class="text-white/70 hover:text-white transition-colors bg-white/10 hover:bg-white/20 p-2 rounded-full" title="Chat schließen">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Chat History --}}
    <div class="flex-1 overflow-y-auto px-4 py-6 space-y-4" x-ref="chatContainer">
        
        @if(empty($messages))
            <div class="flex flex-col items-center justify-center h-full space-y-4 opacity-50 text-center px-6">
                <div class="w-16 h-16 bg-cyan-100 text-cyan-500 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-700">Wie kann ich dir helfen?</p>
                    <p class="text-xs text-gray-500 mt-1">Stelle Fragen zu Produkten, deiner Bestellung oder Reklamationen. Ich bin 24/7 für dich da!</p>
                </div>
            </div>
        @endif

        @foreach($messages as $msg)
            @if($msg['sender'] === 'customer')
                <div class="flex justify-end">
                    <div class="bg-gray-800 text-white text-sm px-4 py-3 rounded-2xl rounded-tr-sm shadow-sm max-w-[85%] leading-relaxed">
                        {{ $msg['text'] }}
                    </div>
                </div>
            @elseif($msg['sender'] === 'ai')
                <div class="flex justify-start space-x-2">
                    <img src="{{ $agentImage ?: asset('shop/ai/images/funki_selfie.png') }}" alt="AI" class="w-8 h-8 rounded-full border border-gray-200 mt-1 shrink-0">
                    <div class="bg-white border border-gray-100 text-gray-700 text-sm px-4 py-3 rounded-2xl rounded-tl-sm shadow-sm max-w-[85%] leading-relaxed prose prose-sm prose-cyan">
                        {!! \Illuminate\Support\Str::markdown($msg['text']) !!}
                    </div>
                </div>
            @elseif($msg['sender'] === 'system')
                <div class="flex justify-center my-3">
                    <span class="bg-gray-100/80 text-gray-500 text-[11px] font-bold tracking-wide uppercase px-3 py-1 rounded-full shadow-sm">
                        {!! \Illuminate\Support\Str::markdown($msg['text']) !!}
                    </span>
                </div>
            @endif
        @endforeach

        @if($isTyping)
            <div class="flex justify-start space-x-2 animate-fade-in-up">
                <img src="{{ $agentImage ?: asset('shop/ai/images/funki_selfie.png') }}" alt="AI" class="w-8 h-8 rounded-full border border-gray-200 mt-1 shrink-0">
                <div class="bg-white border border-gray-100 text-gray-500 text-sm px-4 py-3 rounded-2xl rounded-tl-sm shadow-sm flex items-center space-x-2">
                    <span class="text-xs font-semibold">{{ $agentName }} tippt</span>
                    <div class="flex space-x-1.5 items-center">
                        <span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-bounce"></span>
                        <span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-bounce" style="animation-delay: 0.15s"></span>
                        <span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-bounce" style="animation-delay: 0.3s"></span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Input Box --}}
    <div class="p-4 bg-white border-t border-gray-100 shrink-0">
        @if($isResolved)
            @if(!$ratingSubmitted)
                <div class="text-center w-full px-2 animate-fade-in" x-data="{ hoverRating: 0, selectedRating: @entangle('rating') }">
                    <p class="text-[13px] font-bold text-gray-800 mb-3">Wie hat dir {{ $agentName }} heute geholfen?</p>
                    <div class="flex justify-center items-center space-x-2.5 mb-4" @mouseleave="hoverRating = 0">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    @click="$wire.setRating({{ $i }})"
                                    @mouseenter="hoverRating = {{ $i }}"
                                    class="focus:outline-none transition-transform hover:scale-125 duration-200">
                                <svg class="h-8 w-8 transition-colors duration-200"
                                     :class="(hoverRating >= {{ $i }} || (hoverRating === 0 && selectedRating >= {{ $i }})) ? 'text-amber-400 drop-shadow-sm' : 'text-gray-200'"
                                     viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        @endfor
                    </div>
                    
                    <div x-show="selectedRating > 0" x-collapse x-cloak class="mt-2 text-left">
                        <textarea wire:model="feedbackText" rows="2" class="w-full text-[13px] rounded-xl border border-gray-200 focus:ring-amber-500 focus:border-amber-500 mb-3 resize-none shadow-sm py-2 px-3" placeholder="Wie war deine Erfahrung? (optional)"></textarea>
                        <button type="button" wire:click="submitRating" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 font-bold text-white text-xs py-2.5 px-4 rounded-xl shadow-sm transition-all uppercase tracking-wider">
                            Bewertung absenden
                        </button>
                    </div>
                </div>
            @else
                <div class="text-center p-4 bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl border border-amber-100 shadow-sm mx-1 my-2 animate-fade-in">
                    <i class="bi bi-star-fill text-amber-500 text-2xl mb-2 block drop-shadow-sm"></i>
                    <p class="text-sm font-bold text-amber-900 mb-3">Vielen Dank für dein Feedback!</p>
                    <button wire:click="startNewChat" class="w-full bg-white border border-amber-200 text-amber-700 hover:bg-amber-50 hover:text-amber-800 transition-colors py-2 px-4 rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Neuen Chat starten
                    </button>
                </div>
            @endif
        @else
            @if($guestLimitReached)
                <div class="bg-gradient-to-r from-cyan-50 to-blue-50 border border-cyan-100 rounded-xl p-4 text-center mt-2 mb-2">
                    <p class="text-[13px] text-cyan-900 font-bold mb-3">Sichere deinen Fortschritt!</p>
                    <div class="flex flex-col space-y-2">
                        <a href="{{ route('livewire.auth.register') }}" class="w-full inline-block px-4 py-2.5 bg-gradient-to-r from-cyan-600 to-cyan-500 text-white font-bold text-xs rounded-lg shadow-sm hover:shadow transition-all text-center">
                            Kostenlos Registrieren
                        </a>
                        <a href="{{ route('login') }}" class="text-[11px] text-cyan-700 font-bold hover:underline mt-1">
                            Oder hier einloggen
                        </a>
                    </div>
                </div>
            @else
                <form wire:submit.prevent="sendMessage" class="relative flex items-center">
                    <input wire:model="message" type="text" placeholder="Schreibe deine Nachricht..." 
                           class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-800 rounded-full pl-5 pr-12 py-3.5 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100 transition-all placeholder:text-gray-400"
                           {{ $isTyping ? 'disabled' : '' }}>
                           
                    <button type="submit" 
                            class="absolute right-2 p-2 bg-cyan-500 text-white rounded-full hover:bg-cyan-600 transition-colors shadow-sm disabled:opacity-50"
                            {{ $isTyping ? 'disabled' : '' }}>
                        <svg class="w-4 h-4 translate-x-[1px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </form>
                <div class="text-center mt-2">
                    <span class="text-[9px] text-gray-400 uppercase tracking-widest font-semibold flex justify-center items-center gap-1">
                        <svg class="w-3 h-3 text-cyan-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
                        Mein-Seelenfunke - Support
                    </span>
                </div>
            @endif
        @endif
    </div>
</div>
