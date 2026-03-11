<div class="relative font-sans">
    <div class="fixed z-[9999] flex gap-3 sm:gap-4 pointer-events-none bottom-4 right-4 sm:bottom-6 sm:right-6 flex-col items-end">

        {{-- HAUPTFENSTER --}}
        <div x-data="{ isMaximized: $persist(false) }"
             x-init="$watch('$wire.isOpen', value => { if (value) { setTimeout(() => { const el = document.getElementById('funkira-chat-messages'); if (el) el.scrollTop = el.scrollHeight; }, 150); } })"
             class="pointer-events-auto bg-white rounded-[2rem] sm:rounded-[2.5rem] shadow-[0_20px_60px_rgba(0,0,0,0.15)] border border-slate-100 overflow-hidden flex flex-col transition-all duration-500 transform origin-bottom-right mb-2 sm:mb-4"
             :class="isMaximized
                ? 'w-[calc(100vw-2rem)] h-[85vh] sm:w-[700px] sm:h-[80vh]'
                : 'w-[calc(100vw-2rem)] h-[60vh] sm:w-[360px] sm:h-[550px]'"
             x-show="$wire.isOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-10"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-cloak>

            {{-- Header (Kompaktere Paddings) --}}
            <div class="bg-slate-900 p-4 sm:p-5 flex items-center justify-between relative overflow-hidden shrink-0">
                <div class="absolute inset-0 bg-gradient-to-tr from-cyan-600/30 to-transparent animate-pulse"></div>

                <div class="flex items-center gap-3 sm:gap-4 relative z-10">
                    <div class="w-10 h-10 rounded-xl bg-white/10 p-1 backdrop-blur-xl border border-white/20">
                        <img src="{{ asset('funkira/images/funkira_selfie.png') }}" class="w-full h-full object-cover rounded-lg shadow-sm" alt="Funkira">
                    </div>
                    <div>
                        <h3 class="text-white font-black text-xs sm:text-sm tracking-widest uppercase italic">Funkira Chat</h3>
                        <div class="flex items-center gap-1.5 text-cyan-400">
                            <span class="w-1.5 h-1.5 bg-current rounded-full animate-pulse shadow-[0_0_8px_currentColor]"></span>
                            <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider">Online</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1 relative z-10">
                    {{-- Open Funkira Zentrum Button --}}
                    <button wire:click="openZentrum" title="3D Zentrum öffnen" class="text-slate-400 hover:text-cyan-400 w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10 transition-all mr-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                    </button>

                    <button @click="isMaximized = !isMaximized" class="text-slate-400 hover:text-white w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10 transition-all">
                        <svg x-show="!isMaximized" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 sm:w-5 sm:h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                        <svg x-show="isMaximized" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 sm:w-5 sm:h-5">
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
            <div class="flex bg-slate-50 p-1.5 sm:p-2 gap-1.5 sm:gap-2 border-b border-slate-100 shrink-0">
                <button wire:click="setMode('chat')" class="flex-1 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl text-[9px] sm:text-[10px] font-black uppercase tracking-wider transition-all {{ $activeMode === 'chat' ? 'bg-white shadow-sm text-cyan-600 border border-cyan-100' : 'text-slate-400 hover:bg-slate-100' }}">
                    Chat
                </button>
                @if(auth()->guard('admin')->check())
                    <button wire:click="setMode('logs')" class="flex-1 py-2 sm:py-2.5 rounded-xl sm:rounded-2xl text-[9px] sm:text-[10px] font-black uppercase tracking-wider transition-all {{ $activeMode === 'logs' ? 'bg-white shadow-sm text-cyan-600 border border-cyan-100' : 'text-slate-400 hover:bg-slate-100' }}">
                        System Log
                    </button>
                @endif
            </div>

            <div class="flex-1 overflow-hidden flex flex-col bg-white">
                @if($activeMode === 'chat')
                    <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 sm:space-y-5 bg-slate-50/20 custom-scrollbar" id="funkira-chat-messages">
                        @foreach($messages as $index => $msg)
                            <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[100%] sm:max-w-[85%] flex flex-col gap-2">
                                    <div class="rounded-[1.5rem] px-4 py-2.5 sm:px-5 sm:py-3 text-xs sm:text-sm shadow-sm {{ $msg['role'] === 'user' ? 'bg-slate-900 text-white rounded-br-none self-end' : 'bg-white text-slate-700 border border-slate-100 rounded-bl-none font-medium self-start w-full' }}">
                                        @php
                                            $content = $msg['content'];
                                            
                                            // 1. Extrahiere [COMPONENT]
                                            $hasComponent = preg_match('/\[COMPONENT\](.*?)\[\/COMPONENT\]/is', $content, $matches);
                                            $componentName = $hasComponent ? trim($matches[1]) : null;
                                            
                                            // Entferne den Component String aus dem Text
                                            $cleanContent = trim(preg_replace('/\[COMPONENT\].*?\[\/COMPONENT\]/is', '', $content));
                                            
                                            // 2. Escape HTML, aber erlaube [TEXTBOX] Rendering
                                            $escapedContent = e($cleanContent);
                                            $escapedContent = preg_replace('/\[TEXTBOX\](.*?)\[\/TEXTBOX\]/is', '<textarea readonly class="w-full mt-2 mb-2 p-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl text-slate-800 font-mono shadow-inner transition-all hover:bg-white focus:ring-2 focus:ring-cyan-500/20 outline-none" rows="2" onclick="this.select()">$1</textarea>', $escapedContent);
                                            
                                            // 3. Extrahiere [NAVIGATE] für visuelles Rendering
                                            $hasNavigate = preg_match('/\[NAVIGATE\](.*?)\[\/NAVIGATE\]/is', $escapedContent, $navMatches);
                                            $escapedContent = preg_replace('/\[NAVIGATE\].*?\[\/NAVIGATE\]/is', '', $escapedContent);
                                        @endphp
                                        
                                        @if(!empty(trim($escapedContent)))
                                            {!! nl2br($escapedContent) !!}
                                        @endif
                                        
                                        @if($hasNavigate)
                                            <div class="mt-2 text-[10px] text-cyan-600 font-bold uppercase tracking-wider flex items-center gap-2 animate-pulse">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.438 4.438 0 002.946-2.946 4.493 4.493 0 004.306-1.758q.15-.15.3-.297a9.02 9.02 0 01-5.096-5.096c-.1.1-.203.203-.298.3" />
                                                </svg>
                                                Sprungsequenz initiiert...
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if($isTyping)
                            <div class="flex justify-start animate-pulse">
                                <div class="bg-slate-100 rounded-[1.5rem] px-4 py-2.5 text-slate-400 text-[10px] font-bold uppercase tracking-widest">Funkira analysiert...</div>
                            </div>
                        @endif
                    </div>

                    {{-- Input Bereich (Kompakter) --}}
                    <div class="p-3 sm:p-4 bg-white border-t border-slate-50 shrink-0">
                        <form wire:submit.prevent="sendMessage" class="relative flex items-center bg-slate-100 rounded-[1.5rem] p-1.5 shadow-inner transition-all group focus-within:bg-white focus-within:ring-2 focus-within:ring-cyan-500/10">
                            <input wire:model="input" type="text" placeholder="Mission oder Frage?" class="flex-1 bg-transparent border-0 pl-4 py-2.5 sm:py-3 text-xs sm:text-sm focus:ring-0 outline-none">

                            <button type="submit" class="bg-gradient-to-r from-cyan-600 to-blue-600 text-white w-10 h-10 sm:w-11 sm:h-11 rounded-full shadow-lg hover:from-cyan-500 hover:to-blue-500 active:scale-95 transition-all flex items-center justify-center group/send shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 transition-transform group-hover/send:translate-x-0.5 group-hover/send:-translate-y-0.5">
                                    <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @elseif($activeMode === 'logs')
                    <div class="flex-1 overflow-y-auto p-0 bg-slate-50/30 custom-scrollbar">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead class="sticky top-0 bg-white shadow-sm z-10">
                            <tr class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <th class="px-4 sm:px-5 py-3">Aktivität</th>
                                <th class="px-4 sm:px-5 py-3 text-right">Status</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                            @forelse($this->history as $entry)
                                <tr class="hover:bg-white transition-colors">
                                    <td class="px-4 sm:px-5 py-3 sm:py-4">
                                        <div class="flex items-center gap-3">
                                            @php
                                                $icon = match($entry->status) {
                                                    'success' => 'bi-check-circle-fill text-green-500',
                                                    'error' => 'bi-exclamation-triangle-fill text-red-500',
                                                    'running' => 'bi-arrow-repeat text-cyan-500 animate-spin',
                                                    default => 'bi-info-circle-fill text-slate-400'
                                                };
                                            @endphp
                                            <i class="bi {{ $icon }} text-sm sm:text-base"></i>
                                            <div>
                                                <span class="block font-black text-slate-900 leading-none mb-1 text-[11px] sm:text-xs">{{ $entry->title }}</span>
                                                <span class="text-[8px] sm:text-[9px] text-slate-400 font-mono">{{ $entry->started_at->format('H:i:s') }} — {{ $entry->started_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-5 py-3 sm:py-4 text-right">
                                        <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest {{ $entry->status === 'success' ? 'text-green-600' : 'text-red-500' }}">
                                            {{ $entry->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="p-8 sm:p-10 text-center text-slate-400 italic text-xs">Noch keine Logs vorhanden.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- TRIGGER BUTTON / MINI AVATAR --}}
        <div class="relative items-center flex pointer-events-auto" 
             @mouseenter="showMenu = true" 
             @mouseleave="showMenu = false"
             x-on:funkira-navigate.window="setTimeout(() => { window.location.href = $event.detail.url; }, 1500)"
             x-data="{ 
                 showMenu: false,
                 isListening: $persist(false),
                 isMuted: $persist(false),
                 recognition: null,
                 synthesis: window.speechSynthesis,
                 toggleMute() {
                     this.isMuted = !this.isMuted;
                     if(this.isMuted) {
                         this.synthesis.cancel();
                     }
                 },
                 init() {
                     // Auto-Restart on Page load if the user left it 'Listening'
                     if (this.isListening) {
                         setTimeout(() => { this.initSpeech(); this.toggleListening(true); }, 500);
                     }
                 },
                 initSpeech() {
                     const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                     if (SpeechRecognition) {
                         this.recognition = new SpeechRecognition();
                         this.recognition.lang = 'de-DE';
                         this.recognition.continuous = true;
                         this.recognition.interimResults = false;
                         
                         this.recognition.onstart = () => { this.isListening = true; };
                         this.recognition.onend = () => { 
                             // Auto-Restart unless manually stopped
                             if (this.isListening) {
                                 try { this.recognition.start(); } catch(e) { this.isListening = false; }
                             }
                         };
                         
                         this.recognition.onresult = (event) => {
                             const transcript = event.results[event.results.length - 1][0].transcript;
                             if(transcript.trim() !== '') {
                                 @this.set('input', transcript);
                                 @this.call('sendMessage');
                             }
                         };
                     } else {
                         console.error('Speech Recognition API not supported.');
                     }
                 },
                 speakResponse(text) {
                     if (this.isMuted) return;

                     this.synthesis.cancel(); // Stop old speeches
                     
                     // Filter out all tags: [COMPONENT], [NAVIGATE], [TEXTBOX], [EVENT] and md formatting
                     let cleanText = text.replace(/\[COMPONENT\].*?\[\/COMPONENT\]/gs, 'Visualisiere Komponente.');
                     cleanText = cleanText.replace(/\[NAVIGATE\].*?\[\/NAVIGATE\]/gs, 'Navigiere dorthin.');
                     cleanText = cleanText.replace(/\[TEXTBOX\].*?\[\/TEXTBOX\]/gs, 'Zeige Daten im Textfeld.');
                     cleanText = cleanText.replace(/\[EVENT\].*?\[\/EVENT\]/gs, '');
                     cleanText = cleanText.replace(/[*_#`~>]/g, '')
                                          .replace(/%0?0|\0/g, '')
                                          .replace(/\b([0-9\.]+)\s*(?:H|h)\b/g, '$1 Stunden')
                                          .replace(/\b([0-9\.]+)\s*[Mm](?=\s|$|[.,!?])/g, '$1 Minuten')
                                          // Date Formatter: 20.03.2024 -> 20 Punkt 03 Punkt 2024 (Damit sie es nicht als 20 Millionen 32tausend liest)
                                          .replace(/\b(\d{1,2})\.(\d{1,2})\.(\d{4})\b/g, '$1. $2. $3');

                     const utterance = new SpeechSynthesisUtterance(cleanText);
                     utterance.lang = 'de-DE';

                     const voices = this.synthesis.getVoices();
                     const germanVoice = voices.find(v => v.lang === 'de-DE' && (v.name.includes('Google') || v.name.includes('Neural')));
                     if (germanVoice) utterance.voice = germanVoice;

                     utterance.rate = 1.05;  
                     utterance.pitch = 0.95; 

                     // Temporarily pause mic so she doesn't hear herself
                     if (this.recognition && this.isListening) {
                         this.recognition.stop();
                     }

                     utterance.onend = () => {
                         if (this.isListening) {
                             setTimeout(() => {
                                 try { this.recognition.start(); } catch(e) {}
                             }, 300);
                         }
                     };

                     this.synthesis.speak(utterance);
                 },
                 toggleListening(forceStart = false) {
                     if (!this.recognition) this.initSpeech();
                     
                     if (this.isListening && !forceStart) {
                         this.isListening = false; // Intentionally turning off
                         try { this.recognition.stop(); } catch(e) {}
                     } else {
                         this.isListening = true; // Intentionally turning on
                         try { 
                             this.recognition.start(); 
                         } catch(e) { 
                             if(e.name === 'InvalidStateError') {
                                 // Already started, ignore
                                 console.log('Mic already active.');
                             } else {
                                 console.error(e); 
                                 this.isListening = false; 
                             }
                         }
                     }
                 }
             }"
             @funkira-center-opened.window="if(isListening) { toggleListening(false); }"
             @funkira-spoke.window="speakResponse($event.detail.text)">
                 
            {{-- HOVER MENÜ --}}
            <div x-show="showMenu && !$wire.isOpen" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-4"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-4"
                 class="absolute right-[110%] bottom-0 mb-1 mr-2 bg-slate-900/90 backdrop-blur-xl border border-white/10 p-2 rounded-2xl shadow-2xl flex flex-col gap-1 w-48 z-20" x-cloak>
                <div class="px-2 py-1.5 border-b border-white/10 mb-1">
                    <p class="text-[9px] text-cyan-400 font-extrabold uppercase tracking-widest leading-none">Funkira Core</p>
                    <p class="text-[8px] text-slate-400">System Online</p>
                </div>
                <button @click="toggleListening" class="text-left w-full px-2 py-2 text-xs text-white hover:bg-white/10 rounded-lg transition-colors flex items-center justify-between group">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" :class="isListening ? 'text-red-400 animate-pulse' : 'text-cyan-400'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" /></svg>
                        <span x-text="isListening ? 'Lauscht...' : 'Zuhören'"></span>
                    </div>
                    <div class="relative inline-flex h-4 w-7 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out" :class="isListening ? 'bg-red-500' : 'bg-slate-600'">
                        <span class="inline-block h-3 w-3 transform rounded-full bg-white transition duration-200 ease-in-out" :class="isListening ? 'translate-x-3.5' : 'translate-x-0.5'"></span>
                    </div>
                </button>
                <button @click="toggleMute" class="text-left w-full px-2 py-2 text-xs text-white hover:bg-white/10 rounded-lg transition-colors flex items-center justify-between group">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" :class="isMuted ? 'text-slate-500' : 'text-cyan-400'"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                        <span x-text="isMuted ? 'Ton Aus' : 'Ton An'"></span>
                    </div>
                    <div class="relative inline-flex h-4 w-7 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out" :class="isMuted ? 'bg-slate-600' : 'bg-green-500'">
                        <span class="inline-block h-3 w-3 transform rounded-full bg-white transition duration-200 ease-in-out" :class="isMuted ? 'translate-x-0.5' : 'translate-x-3.5'"></span>
                    </div>
                </button>
                <button wire:click="openZentrum" class="text-left w-full px-2 py-2 text-xs text-white hover:bg-white/10 rounded-lg transition-colors flex items-center justify-between group border-t border-white/5 pt-2 mt-1">
                    <span>Aktivierungswort</span>
                    <span class="text-[9px] bg-white/10 px-1.5 py-0.5 rounded text-slate-300">Funkira</span>
                </button>
                <a href="{{ route('admin.funkira-log') }}" class="text-left w-full px-2 py-2 text-xs text-white hover:bg-white/10 rounded-lg transition-colors flex items-center gap-2 mt-1 group">
                    <i class="bi bi-card-text text-emerald-400 group-hover:text-emerald-300"></i>
                    <span>Funkira Log</span>
                </a>
                <a href="{{ route('admin.funkira-methods') }}" class="text-left w-full px-2 py-2 text-xs text-white hover:bg-white/10 rounded-lg transition-colors flex items-center gap-2 group">
                    <i class="bi bi-hdd-network text-purple-400 group-hover:text-purple-300"></i>
                    <span>Fähigkeiten-Matrix</span>
                </a>
                <button wire:click="openZentrum" class="text-left w-full px-2 py-2 text-xs text-cyan-400 hover:bg-cyan-500/20 hover:text-cyan-300 rounded-lg transition-colors flex items-center gap-2 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                    Zentrum öffnen
                </button>
            </div>

            <button wire:click="toggleChat" class="relative outline-none shrink-0 transition-all active:scale-95 group/btn z-30">
                <div class="absolute inset-0 rounded-full blur-xl opacity-30 group-hover/btn:opacity-60 transition-colors duration-500 animate-pulse" :class="isListening ? 'bg-red-600' : '{{ $isTyping ? "bg-pink-500" : "bg-emerald-400" }}'"></div>

                {{-- CSS ORB 3D CORE --}}
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-[1.2rem] sm:rounded-[1.4rem] shadow-2xl transition-all duration-500 transform group-hover/btn:scale-105 flex items-center justify-center relative z-10 overflow-hidden shrink-0 bg-slate-900 border" :class="isListening ? 'border-red-500/50' : '{{ $isTyping ? "border-pink-500/50" : "border-emerald-400/30" }}'">
                    
                    {{-- Inner Glowing Sphere --}}
                    <div class="absolute inset-2 rounded-full border animate-[spin_4s_linear_infinite] group-hover/btn:[animation-duration:15s]" :class="isListening ? 'border-red-500/60' : '{{ $isTyping ? "border-pink-400/60" : "border-emerald-400/40" }}'"></div>
                    <div class="absolute inset-3 rounded-full border animate-[spin_3s_linear_infinite_reverse] group-hover/btn:[animation-duration:12s]" :class="isListening ? 'border-orange-500/50' : '{{ $isTyping ? "border-purple-400/50" : "border-teal-400/30" }}'"></div>
                    
                    {{-- Core Light --}}
                    <div class="absolute w-6 h-6 rounded-full blur-md animate-pulse transition-colors duration-700" :class="isListening ? 'bg-red-500 scale-125' : '{{ $isTyping ? "bg-pink-400 scale-110" : "bg-emerald-400" }}'"></div>
                    <div class="absolute w-3 h-3 bg-white rounded-full blur-[2px]"></div>
                    
                    {{-- Grid Lines Overlay --}}
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_30%,rgba(0,0,0,0.8)_100%)]"></div>

                    {{-- AI Badge --}}
                    <div x-show="!$wire.isOpen && !isListening" x-transition.opacity class="absolute top-1 right-1 flex h-4 w-4 sm:h-5 sm:w-5">
                        <span class="animate-ping absolute inset-0 rounded-full opacity-75 {{ $isTyping ? 'bg-pink-400' : 'bg-emerald-400' }}"></span>
                        <span class="relative h-4 w-4 sm:h-5 sm:w-5 bg-slate-800 border sm:border rounded-full flex items-center justify-center text-[6px] sm:text-[8px] font-black italic shadow-lg {{ $isTyping ? 'text-pink-400 border-pink-400/30' : 'text-emerald-400 border-emerald-400/30' }}">
                            AI
                        </span>
                    </div>
                </div>
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('message-sent', () => {
                setTimeout(() => {
                    const el = document.getElementById('funkira-chat-messages');
                    if(el) { el.scrollTop = el.scrollHeight; }
                }, 100);
            });
        });
    </script>

    <style>
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</div>
