<div class="p-4 sm:p-6 lg:p-10 min-h-[85vh] flex flex-col relative z-10 font-sans antialiased text-gray-300"
     x-init="
        if (typeof window.Echo !== 'undefined') {
            window.Echo.private('customer.{{ auth()->guard('customer')->id() }}')
                .listen('.TicketMessageSent', (e) => {
                    $wire.receiveMessage(e);
                });
        }
     "
>
    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1/index.js"></script>

    {{-- HEADER --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4 animate-fade-in-up">
        <div>
            <a href="{{ route('customer.dashboard') }}" class="inline-flex items-center gap-2 text-[10px] sm:text-xs text-gray-400 font-bold uppercase tracking-widest hover:text-primary transition-colors mb-4">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Zurück zur Manufaktur
            </a>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-black text-white tracking-tight flex items-center gap-3">
                <span class="text-primary drop-shadow-[0_0_15px_rgba(197,160,89,0.5)]">💌</span> Support Desk
            </h1>
            <p class="text-gray-400 mt-2 text-xs sm:text-sm uppercase tracking-widest font-bold">Direkter Draht zu unseren Experten</p>
        </div>

        <div class="flex items-center gap-4">
            <label class="flex items-center gap-3 cursor-pointer group">
                <div class="text-right">
                    <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 group-hover:text-white transition-colors">E-Mail Infos</span>
                    <span class="block text-xs font-bold {{ $emailNotifications ? 'text-emerald-400' : 'text-gray-600' }}">{{ $emailNotifications ? 'Aktiviert' : 'Deaktiviert' }}</span>
                </div>
                <div class="relative">
                    <input type="checkbox" wire:model.live="emailNotifications" class="sr-only">
                    <div class="block bg-gray-800 w-10 h-6 rounded-full border border-gray-700 transition-colors {{ $emailNotifications ? 'bg-primary' : '' }}"></div>
                    <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform {{ $emailNotifications ? 'transform translate-x-4' : '' }}"></div>
                </div>
            </label>

            @if($viewMode === 'list')
                <button wire:click="setMode('create')" class="px-6 py-3 bg-primary text-gray-900 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-white transition-all shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:-translate-y-1 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                    Ticket
                </button>
            @endif
        </div>
    </div>

    {{-- VIEW: TICKET LISTE --}}
    @if($viewMode === 'list')
        <div class="bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-[2rem] shadow-2xl overflow-hidden animate-fade-in">
            @if($tickets->isEmpty())
                <div class="p-12 text-center flex flex-col items-center">
                    <div class="w-24 h-24 bg-gray-950 border border-gray-800 rounded-full flex items-center justify-center mb-6 shadow-inner"><span class="text-4xl opacity-50">📫</span></div>
                    <h3 class="text-xl font-serif font-bold text-white mb-2">Keine Anfragen vorhanden</h3>
                    <p class="text-gray-500 max-w-md mx-auto text-sm">Du hast bisher noch keinen Kontakt mit unserem Support-Team aufgenommen.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                        <tr class="bg-gray-950/80 border-b border-gray-800 text-[10px] font-black text-gray-500 uppercase tracking-widest">
                            <th class="px-6 py-5 pl-8">Ticket-ID & Betreff</th>
                            <th class="px-6 py-5 hidden md:table-cell">Bezug</th>
                            <th class="px-6 py-5">Status</th>
                            <th class="px-6 py-5 text-right pr-8">Aktion</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                        @foreach($tickets as $t)
                            @php $unread = $t->messages->where('sender_type', '!=', 'customer')->where('is_read_by_customer', false)->count(); @endphp
                            <tr class="hover:bg-gray-800/30 transition-colors group cursor-pointer" wire:click="setMode('chat', '{{ $t->id }}')">
                                <td class="px-6 py-5 pl-8">
                                    <div class="flex items-start gap-4">
                                        @if($unread > 0) <span class="flex w-3 h-3 mt-2 bg-red-500 rounded-full animate-pulse shadow-[0_0_10px_rgba(239,68,68,0.8)] shrink-0" title="Neue Nachricht!"></span> @endif
                                        <div>
                                            <div class="flex items-center gap-3 mb-2">
                                                <div class="inline-flex items-center gap-2 bg-gray-950 border border-primary/50 px-3 py-1 rounded-lg shadow-inner">
                                                    <span class="text-sm text-primary font-mono font-black tracking-wider">{{ $t->ticket_number }}</span>
                                                </div>
                                                <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black">{{ $t->created_at->format('d.m.Y') }}</span>
                                            </div>
                                            <p class="text-sm font-bold text-white group-hover:text-primary transition-colors truncate max-w-[250px] sm:max-w-xs">{{ $t->subject }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 hidden md:table-cell align-top pt-6">
                                    <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">
                                        @if($t->category === 'support') Hilfe & Support
                                        @elseif($t->category === 'return') Retoure / Defekt
                                        @elseif($t->category === 'bug') Systemfehler
                                        @else Allgemeine Frage @endif
                                    </span>
                                </td>
                                <td class="px-6 py-5 align-top pt-6">
                                    @if($t->status === 'open') <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-900/20 border border-blue-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest text-blue-400">In Bearbeitung</span>
                                    @elseif($t->status === 'answered') <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-900/20 border border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest text-emerald-400">Beantwortet</span>
                                    @else <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-800 border border-gray-700 rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-500">Geschlossen</span> @endif
                                </td>
                                <td class="px-6 py-5 pr-8 text-right align-top pt-6">
                                    <button class="p-2 text-gray-500 group-hover:text-primary transition-colors bg-gray-950 border border-gray-800 rounded-lg shadow-inner">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- VIEW: TICKET ERSTELLEN --}}
    @if($viewMode === 'create')
        <div class="max-w-4xl mx-auto w-full bg-gray-900 border border-gray-800 rounded-[2rem] p-6 sm:p-10 shadow-2xl animate-fade-in relative overflow-hidden">
            <h2 class="text-2xl font-serif font-bold text-white mb-8 border-b border-gray-800 pb-4">Wie können wir helfen?</h2>
            <form wire:submit.prevent="createTicket" class="space-y-6 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Art der Anfrage *</label>
                        <select wire:model="newCategory" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                            <option value="support">Hilfe & Support</option>
                            <option value="question">Allgemeine Frage</option>
                            <option value="return">Retoure / Beschädigung</option>
                            <option value="bug">Fehler melden (+5 ✨)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Betroffene Bestellung (Optional)</label>
                        <select wire:model="newOrderId" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                            <option value="">Keine spezielle Bestellung</option>
                            @foreach($orders as $o) <option value="{{ $o->id }}">Bestellung vom {{ $o->created_at->format('d.m.Y') }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Betreff *</label>
                    <input type="text" wire:model="newSubject" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-2">Deine Nachricht *</label>
                    <textarea wire:model="newMessage" rows="6" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none"></textarea>
                </div>
                <div class="pt-4 flex justify-between items-center border-t border-gray-800">
                    <button type="button" wire:click="setMode('list')" class="text-gray-500 font-bold text-xs uppercase tracking-widest hover:text-white">Abbrechen</button>
                    <button type="submit" class="px-8 py-4 bg-primary text-gray-900 rounded-xl font-black text-sm uppercase tracking-widest hover:bg-white transition-all shadow-[0_0_20px_rgba(197,160,89,0.4)]">Ticket absenden</button>
                </div>
            </form>
        </div>
    @endif

    {{-- VIEW: CHAT INTERFACE --}}
    @if($viewMode === 'chat' && $activeTicket)
        <div class="max-w-5xl mx-auto w-full bg-gray-900 border border-gray-800 rounded-[2rem] shadow-2xl flex flex-col h-[75vh] animate-fade-in relative overflow-hidden">

            <div class="bg-gray-950 border-b border-gray-800 p-5 sm:p-6 flex justify-between z-10 shadow-md gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <button wire:click="setMode('list')" class="text-gray-500 hover:text-primary transition-colors p-1 bg-gray-900 rounded-lg shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <div class="inline-flex items-center gap-2 bg-gray-900 border border-primary/50 px-3 py-1 rounded-lg shadow-inner">
                            <span class="text-xs text-primary font-mono font-black">{{ $activeTicket->ticket_number }}</span>
                        </div>
                    </div>
                    <h2 class="text-lg sm:text-xl font-bold text-white pl-10 break-all whitespace-pre-wrap">{{ $activeTicket->subject }}</h2>
                </div>
            </div>

            {{-- AUTO-SCROLL FIX: Startet unten, und scrollt beim Update! --}}
            <div class="flex-1 overflow-y-auto p-4 sm:p-8 space-y-6 custom-scrollbar bg-gray-900/50" id="chat-container"
                 x-data="{
                     scroll() {
                         setTimeout(() => { $el.scrollTop = $el.scrollHeight; }, 50);
                     }
                 }"
                 x-init="scroll()"
                 @ticket-message-received.window="scroll()">

                @foreach($activeTicket->messages as $msg)
                    @php
                        $isOnlyEmojis = mb_strlen(trim($msg->message)) <= 12 && !preg_match('/[\p{L}\p{N}]/u', $msg->message);
                    @endphp

                    @if($msg->sender_type === 'customer')
                        <div class="flex justify-end w-full">
                            <div class="max-w-[85%] sm:max-w-[70%]">
                                <div class="bg-primary/10 border border-primary/30 rounded-2xl rounded-tr-sm p-4 sm:p-5 shadow-lg text-white">
                                    <p class="{{ $isOnlyEmojis ? 'text-5xl py-2' : 'text-sm' }} leading-relaxed break-all whitespace-pre-wrap">{{ $msg->message }}</p>
                                    @if($msg->attachments)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($msg->attachments as $img)
                                                <a href="{{ asset('storage/' . $img) }}" target="_blank" class="block w-20 h-20 rounded-lg overflow-hidden border border-primary/50 hover:opacity-80 transition-opacity"><img src="{{ asset('storage/' . $img) }}" class="w-full h-full object-cover"></a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex justify-end mt-1 px-1"><p class="text-[9px] text-gray-500 font-bold">Du • {{ $msg->created_at->format('H:i') }} Uhr</p></div>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-start w-full">
                            <div class="max-w-[85%] sm:max-w-[70%] flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-200 to-amber-500 flex items-center justify-center shrink-0 shadow-lg border border-gray-900">
                                    <span class="text-gray-900 font-black text-xs">M</span>
                                </div>
                                <div>
                                    <div class="bg-gray-800 border border-gray-700 rounded-2xl rounded-tl-sm p-4 sm:p-5 shadow-lg text-gray-300">
                                        <p class="{{ $isOnlyEmojis ? 'text-5xl py-2' : 'text-sm' }} leading-relaxed break-all whitespace-pre-wrap">{{ $msg->message }}</p>
                                        @if($msg->attachments)
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach($msg->attachments as $img)
                                                    <a href="{{ asset('storage/' . $img) }}" target="_blank" class="block w-20 h-20 rounded-lg overflow-hidden border border-gray-600 hover:opacity-80 transition-opacity"><img src="{{ asset('storage/' . $img) }}" class="w-full h-full object-cover"></a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-[9px] text-gray-500 font-bold mt-1 px-1 text-left">Seelen-Admin • {{ $msg->created_at->format('H:i') }} Uhr</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="bg-gray-950 border-t border-gray-800 p-4 sm:p-5 z-20 relative">
                @if($activeTicket->status === 'closed')
                    <div class="text-center py-3 bg-gray-900 rounded-xl border border-gray-800"><p class="text-gray-500 text-xs font-bold uppercase tracking-widest">Ticket ist geschlossen.</p></div>
                @else
                    <form wire:submit.prevent="sendReply" class="w-full relative">

                        @if($chatAttachments)
                            <div class="mb-3 flex flex-wrap gap-2 bg-gray-900 p-2 rounded-xl border border-gray-800 shadow-inner">
                                @foreach($chatAttachments as $index => $photo)
                                    <div class="relative w-12 h-12 rounded overflow-hidden border border-gray-700 group">
                                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                                        <button type="button" wire:click="removeAttachment({{ $index }}, 'chatAttachments')" class="absolute inset-0 bg-red-500/80 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-end bg-gray-900 border border-gray-700 rounded-3xl p-1.5 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all shadow-inner relative z-30">

                            <label class="cursor-pointer p-2 sm:p-2.5 text-gray-400 hover:text-primary transition-colors rounded-full hover:bg-gray-800 shrink-0 mb-0.5" title="Datei anhängen">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <input type="file" wire:model="chatNewAttachments" multiple class="hidden">
                            </label>

                            <div x-data="{
                                showEmojis: false,
                                activeTab: 'Basis',
                                categories: {
                                    'Basis': ['😀','😂','🤣','😊','😇','😍','🥰','😘','😋','😛','😎','🤔','🙄','😴','😬','🤐','🤯','😢','😭','😡','🤬','🤢','🤮','🤡','👻'],
                                    'Gesten': ['👍','👎','👌','✌️','🤞','🫰','🤟','🤘','🤙','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','👏','🫶','👐','🙌','🤲','🙏','🤝'],
                                    'Symbole': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','❤️‍🩹','💕','💞','💓','💗','💖','💘','💝','✨','🔥','🎉','💯','✅','❌']
                                }
                            }" class="relative shrink-0 mb-0.5">
                                <button type="button" @click="showEmojis = !showEmojis" class="p-2 sm:p-2.5 text-gray-400 hover:text-primary transition-colors rounded-full hover:bg-gray-800 focus:outline-none">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>

                                <div x-show="showEmojis" @click.outside="showEmojis = false" x-transition.opacity
                                     class="absolute bottom-[calc(100%+15px)] left-0 z-[9999] shadow-[0_0_30px_rgba(0,0,0,0.8)] rounded-2xl border border-gray-700 w-[280px] bg-gray-900 flex flex-col overflow-hidden"
                                     style="display: none; height: 320px;">
                                    <div class="flex border-b border-gray-800 bg-gray-950 shrink-0">
                                        <template x-for="(emojis, name) in categories" :key="name">
                                            <button type="button" @click="activeTab = name" :class="activeTab === name ? 'text-primary border-primary' : 'text-gray-500 border-transparent hover:text-gray-300'" class="flex-1 py-3 text-[10px] font-black uppercase tracking-widest transition-colors border-b-2" x-text="name"></button>
                                        </template>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-3 custom-scrollbar grid grid-cols-6 gap-2 content-start">
                                        <template x-for="emoji in categories[activeTab]" :key="emoji">
                                            <button type="button" @click="$wire.set('chatMessage', $wire.chatMessage + emoji); showEmojis = false" class="text-2xl hover:scale-125 transition-transform hover:bg-gray-800 rounded flex items-center justify-center aspect-square" x-text="emoji"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <textarea wire:model="chatMessage"
                                      x-data="{ resize() { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; } }"
                                      x-init="resize()" @input="resize()"
                                      @keydown.enter.prevent="if(!$event.shiftKey) { $wire.sendReply(); }"
                                      rows="1" placeholder="Schreibe eine Nachricht..."
                                      class="flex-1 bg-transparent text-white text-sm px-2 sm:px-3 py-2.5 sm:py-3 resize-none focus:outline-none custom-scrollbar max-h-32 min-h-[44px] break-all whitespace-pre-wrap leading-relaxed"></textarea>

                            <button type="submit" wire:loading.attr="disabled" class="w-10 h-10 sm:w-11 sm:h-11 ml-1 sm:ml-2 rounded-full bg-primary text-gray-900 flex items-center justify-center hover:scale-110 hover:-rotate-12 transition-all duration-300 shrink-0 shadow-[0_0_15px_rgba(197,160,89,0.3)] hover:shadow-[0_0_25px_rgba(197,160,89,0.8)] disabled:opacity-50 disabled:hover:scale-100 disabled:hover:rotate-0 mb-0.5 focus:outline-none">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
