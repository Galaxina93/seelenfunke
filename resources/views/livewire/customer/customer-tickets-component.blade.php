<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 animate-fade-in-up min-h-[85vh] flex flex-col relative z-10 font-sans antialiased text-gray-700 dark:text-gray-300"
     x-data="{ showCloseModal: false }"
     @close-ticket-modal-hide.window="showCloseModal = false"
     @customer-ticket-message-received.window="$wire.receiveMessage($event.detail)"
>
    <script type="module" src="{{ asset('vendor/emoji-picker-element/index.js') }}"></script>

    {{-- HEADER --}}
    <div class="mb-8 lg:mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-6">
        <div>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                Hilfe & Support
            </h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-xs sm:text-sm uppercase tracking-widest font-bold">Direkter Draht zu unseren Experten</p>
        </div>

        <div class="flex items-center gap-4">
            @if($viewMode === 'create')
                <button wire:click="setMode('list')" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-xl font-bold text-xs uppercase tracking-wider transition-all flex items-center gap-2 shadow-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Zurück zur Übersicht
                </button>
            @elseif($viewMode === 'list')
                <label class="flex items-center gap-2.5 px-3.5 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl cursor-pointer group shadow-sm" title="E-Mail-Benachrichtigung bei Support-Antworten ein- oder ausschalten">
                    <div class="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="text-left pr-1">
                        <span class="block text-[9px] font-black uppercase tracking-wider text-gray-400">E-Mail-Updates</span>
                        <span class="block text-[11px] font-bold {{ $emailNotifications ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}">{{ $emailNotifications ? 'Aktiviert' : 'Deaktiviert' }}</span>
                    </div>
                    <div class="relative w-9 h-5 ml-2 shrink-0">
                        <input type="checkbox" wire:model.live="emailNotifications" class="sr-only">
                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-800 rounded-full border border-gray-300 dark:border-gray-700 transition-colors {{ $emailNotifications ? '!bg-primary' : '' }}"></div>
                        <div class="absolute left-0.5 top-0.5 bg-white w-4 h-4 rounded-full shadow transition-transform duration-200 ease-in-out {{ $emailNotifications ? 'transform translate-x-4' : '' }}"></div>
                    </div>
                </label>

                <button wire:click="setMode('create')" class="px-6 py-3 bg-primary text-gray-900 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-gray-900 hover:text-white dark:hover:bg-white dark:hover:text-gray-900 transition-all shadow-sm dark:shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:-translate-y-1 flex items-center gap-2 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                    Neues Ticket
                </button>
            @elseif($viewMode === 'chat')
                <button wire:click="setMode('list')" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-xl font-bold text-xs uppercase tracking-wider transition-all flex items-center gap-2 shadow-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Alle Tickets
                </button>
            @endif
        </div>
    </div>

    {{-- VIEW: TICKET LISTE --}}
    @if($viewMode === 'list')
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[2rem] shadow-sm dark:shadow-2xl overflow-hidden">
            @if($tickets->isEmpty())
                <div class="p-12 text-center flex flex-col items-center">
                    <div class="w-24 h-24 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-full flex items-center justify-center mb-6 shadow-inner"><span class="text-4xl opacity-70">📫</span></div>
                    <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mb-2">Keine Anfragen vorhanden</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto text-sm">Du hast bisher noch keinen Kontakt mit unserem Support-Team aufgenommen.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                        <tr class="bg-gray-50 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                            <th class="px-6 py-5 pl-8">Ticket-ID & Betreff</th>
                            <th class="px-6 py-5 hidden md:table-cell">Bezug</th>
                            <th class="px-6 py-5">Status</th>
                            <th class="px-6 py-5 text-right pr-8">Aktion</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/50">
                        @foreach($tickets as $t)
                            @php $unread = $t->messages->where('sender_type', '!=', 'customer')->where('is_read_by_customer', false)->count(); @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors group cursor-pointer" wire:click="setMode('chat', '{{ $t->id }}')">
                                <td class="px-6 py-5 pl-8">
                                    <div class="flex items-start gap-4">
                                        @if($unread > 0) <span class="flex w-3 h-3 mt-2 bg-red-500 rounded-full animate-pulse shadow-[0_0_10px_rgba(239,68,68,0.8)] shrink-0" title="Neue Nachricht!"></span> @endif
                                        <div>
                                            <div class="flex items-center gap-3 mb-2">
                                                <div class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-950 border border-primary/30 dark:border-primary/50 px-3 py-1 rounded-lg shadow-sm dark:shadow-inner">
                                                    <span class="text-sm text-primary font-mono font-black tracking-wider">{{ $t->ticket_number }}</span>
                                                </div>
                                                <span class="text-[9px] text-gray-400 dark:text-gray-500 uppercase tracking-widest font-black">{{ $t->created_at->format('d.m.Y') }}</span>
                                            </div>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-primary transition-colors truncate max-w-[250px] sm:max-w-xs">{{ $t->subject }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 hidden md:table-cell align-top pt-6">
                                    <span class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">
                                        @if($t->category === 'support') Hilfe & Support
                                        @elseif($t->category === 'return') Retoure / Defekt
                                        @elseif($t->category === 'bug') Systemfehler
                                        @else Allgemeine Frage @endif
                                    </span>
                                </td>
                                <td class="px-6 py-5 align-top pt-6">
                                    @if($t->status === 'open') <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400">In Bearbeitung</span>
                                    @elseif($t->status === 'answered') <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 rounded-lg text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Beantwortet</span>
                                    @else <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Geschlossen</span> @endif
                                </td>
                                <td class="px-6 py-5 pr-8 text-right align-top pt-6">
                                    <button class="p-2 text-gray-400 dark:text-gray-500 group-hover:text-primary transition-colors bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm">
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
        <div class="max-w-4xl mx-auto w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-10 shadow-sm dark:shadow-2xl relative overflow-hidden">
            <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-8 border-b border-gray-200 dark:border-gray-800 pb-4">Wie können wir helfen?</h2>
            <form wire:submit.prevent="createTicket" class="space-y-6 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Art der Anfrage *</label>
                        <select wire:model="newCategory" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                            <option value="support">Hilfe & Support</option>
                            <option value="question">Allgemeine Frage</option>
                            <option value="return">Retoure / Beschädigung</option>
                            <option value="bug">Fehler melden</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Betroffene Bestellung (Optional)</label>
                        <select wire:model="newOrderId" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                            <option value="">Keine spezielle Bestellung</option>
                            @foreach($orders as $o)
                                @php
                                    $itemNames = $o->items->pluck('product_name')->filter()->unique();
                                    $itemsSummary = $itemNames->take(2)->join(', ');
                                    if ($itemNames->count() > 2) {
                                        $itemsSummary .= ' (+' . ($itemNames->count() - 2) . ' weitere)';
                                    }
                                    $priceFormatted = number_format($o->total_price / 100, 2, ',', '.') . ' €';
                                @endphp
                                <option value="{{ $o->id }}">
                                    #{{ $o->order_number }} · {{ $o->created_at->format('d.m.Y, H:i') }} Uhr ({{ $priceFormatted }})@if(!empty($itemsSummary)) – {{ \Illuminate\Support\Str::limit($itemsSummary, 45) }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Betreff *</label>
                    <input type="text" wire:model="newSubject" class="w-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors @error('newSubject') border-2 border-red-500 @else border border-gray-200 dark:border-gray-800 @enderror">
                    @error('newSubject') <span class="text-red-500 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Deine Nachricht *</label>
                    <textarea wire:model="newMessage" rows="6" class="w-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors @error('newMessage') border-2 border-red-500 @else border border-gray-200 dark:border-gray-800 @enderror"></textarea>
                    @error('newMessage') <span class="text-red-500 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                </div>

                {{-- E-Mail-Benachrichtigung Präferenz (Kontextuell & professionell platziert) --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 rounded-2xl flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-gray-900 dark:text-white">E-Mail-Benachrichtigung bei Support-Antworten</span>
                            <span class="block text-[11px] text-gray-500 dark:text-gray-400">Wir senden dir eine Benachrichtigung an <strong class="text-gray-700 dark:text-gray-300">{{ auth()->user()->email }}</strong>, sobald unser Team antwortet.</span>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" wire:model.live="emailNotifications" class="sr-only">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-800 rounded-full border border-gray-300 dark:border-gray-700 transition-colors {{ $emailNotifications ? '!bg-primary' : '' }}"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full shadow transition-transform {{ $emailNotifications ? 'transform translate-x-5' : '' }}"></div>
                    </label>
                </div>

                <div class="pt-4 flex justify-between items-center border-t border-gray-200 dark:border-gray-800">
                    <button type="button" wire:click="setMode('list')" class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest hover:text-gray-900 dark:hover:text-white transition-colors">Abbrechen</button>
                    <button type="submit" class="px-8 py-4 bg-primary text-gray-900 rounded-xl font-black text-sm uppercase tracking-widest hover:bg-gray-900 hover:text-white dark:hover:bg-white dark:hover:text-gray-900 transition-all shadow-sm dark:shadow-[0_0_20px_rgba(197,160,89,0.4)]">SupportTicket absenden</button>
                </div>
            </form>
        </div>
    @endif

    {{-- VIEW: CHAT INTERFACE --}}
    @if($viewMode === 'chat' && $activeTicket)
        <div class="max-w-5xl mx-auto w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] shadow-sm dark:shadow-2xl flex flex-col h-[75vh] animate-fade-in relative overflow-hidden">

            <div class="bg-gray-50 dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 p-5 sm:p-6 flex justify-between z-10 shadow-sm gap-4">
                <div class="flex items-center gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <button wire:click="setMode('list')" class="text-gray-500 dark:text-gray-400 hover:text-primary transition-colors p-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div class="inline-flex items-center gap-2 bg-white dark:bg-gray-900 border border-primary/40 px-3 py-1 rounded-lg shadow-sm dark:shadow-inner">
                                <span class="text-xs text-primary font-mono font-black">{{ $activeTicket->ticket_number }}</span>
                            </div>
                        </div>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white pl-10 break-all whitespace-pre-wrap">{{ $activeTicket->subject }}</h2>
                    </div>
                </div>

                @if($activeTicket->status !== 'closed')
                <div class="shrink-0 flex items-center">
                    <button @click="showCloseModal = true" class="px-3 py-2 bg-white dark:bg-gray-900 border border-red-300 dark:border-red-500/50 hover:bg-red-500 hover:text-white text-red-500 dark:text-red-400 rounded-lg text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all shadow-sm">Ticket schließen</button>
                </div>
                @endif
            </div>

            {{-- AUTO-SCROLL FIX: Startet unten, und scrollt beim Update! --}}
            <div class="flex-1 overflow-y-auto p-4 sm:p-8 space-y-6 custom-scrollbar bg-slate-50/60 dark:bg-gray-900/50" id="chat-container"
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
                                <div class="bg-primary/15 dark:bg-primary/10 border border-primary/30 rounded-2xl rounded-tr-sm p-4 sm:p-5 shadow-sm text-gray-900 dark:text-white">
                                    <p class="{{ $isOnlyEmojis ? 'text-5xl py-2' : 'text-sm' }} leading-relaxed break-all whitespace-pre-wrap">{{ $msg->message }}</p>
                                    @if($msg->attachments)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($msg->attachments as $img)
                                                <a href="{{ asset('storage/' . $img) }}" target="_blank" class="block w-20 h-20 rounded-lg overflow-hidden border border-primary/50 hover:opacity-80 transition-opacity"><img src="{{ asset('storage/' . $img) }}" class="w-full h-full object-cover"></a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex justify-end mt-1 px-1"><p class="text-[9px] text-gray-400 dark:text-gray-500 font-bold">Du • {{ $msg->created_at->format('H:i') }} Uhr</p></div>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-start w-full">
                            <div class="max-w-[85%] sm:max-w-[70%] flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center shrink-0 shadow-sm border border-gray-200 dark:border-gray-900">
                                    <span class="text-white font-black text-xs">M</span>
                                </div>
                                <div>
                                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl rounded-tl-sm p-4 sm:p-5 shadow-sm text-gray-800 dark:text-gray-200">
                                        <p class="{{ $isOnlyEmojis ? 'text-5xl py-2' : 'text-sm' }} leading-relaxed break-all whitespace-pre-wrap">{{ $msg->message }}</p>
                                        @if($msg->attachments)
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach($msg->attachments as $img)
                                                    <a href="{{ asset('storage/' . $img) }}" target="_blank" class="block w-20 h-20 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 hover:opacity-80 transition-opacity"><img src="{{ asset('storage/' . $img) }}" class="w-full h-full object-cover"></a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-[9px] text-gray-400 dark:text-gray-500 font-bold mt-1 px-1 text-left">Seelen-Admin • {{ $msg->created_at->format('H:i') }} Uhr</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="bg-gray-50 dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800 p-4 sm:p-5 z-20 relative">
                @if($activeTicket->status === 'closed')
                    @if(!$ratingSubmitted)
                        <div class="text-center w-full px-2 animate-[fade-in_0.5s_ease-out]" x-data="{ hoverRating: 0, selectedRating: @entangle('rating') }">
                            <p class="text-[13px] font-bold text-gray-700 dark:text-gray-300 mb-3">Wie hat dir der Seelenfunke Support geholfen?</p>
                            <div class="flex justify-center items-center space-x-2.5 mb-4" @mouseleave="hoverRating = 0">
                                @for($i = 1; $i <= 5; $i++)
                                    <button type="button"
                                            @click="$wire.setRating({{ $i }})"
                                            @mouseenter="hoverRating = {{ $i }}"
                                            class="focus:outline-none transition-transform hover:scale-125 duration-200">
                                        <svg class="h-8 w-8 transition-colors duration-200"
                                             :class="(hoverRating >= {{ $i }} || (hoverRating === 0 && selectedRating >= {{ $i }})) ? 'text-amber-400 drop-shadow-[0_0_8px_rgba(251,191,36,0.5)]' : 'text-gray-300 dark:text-gray-700'"
                                             viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                @endfor
                            </div>

                            <div x-show="selectedRating > 0" style="display:none;" x-transition.opacity class="mt-2 text-left bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-3 rounded-2xl shadow-sm">
                                <textarea wire:model="feedbackText" rows="2" class="w-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white text-[13px] rounded-xl border border-gray-200 dark:border-gray-800 focus:ring-amber-500 focus:border-amber-500 mb-3 resize-none shadow-sm py-2 px-3 placeholder-gray-400 dark:placeholder-gray-600" placeholder="Wie war deine Erfahrung? (optional)"></textarea>
                                <button type="button" wire:click="submitRating" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 font-bold text-white text-xs py-2.5 px-4 rounded-xl shadow-md transition-all uppercase tracking-wider">
                                    Bewertung absenden
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="text-center p-4 bg-gray-100 dark:bg-gradient-to-r dark:from-gray-900 dark:to-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm mx-1 my-2 animate-[fade-in_0.5s_ease-out]">
                            <svg class="h-8 w-8 mx-auto text-amber-500 mb-2 drop-shadow-[0_0_8px_rgba(251,191,36,0.6)]" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-300 mb-2">Vielen Dank für dein Feedback!</p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-widest">SupportTicket ist geschlossen.</p>
                        </div>
                    @endif
                @else
                    <form wire:submit.prevent="sendReply" class="w-full relative">

                        @if($chatAttachments)
                            <div class="mb-3 flex flex-wrap gap-2 bg-gray-100 dark:bg-gray-900 p-2 rounded-xl border border-gray-200 dark:border-gray-800 shadow-inner">
                                @foreach($chatAttachments as $index => $photo)
                                    <div class="relative w-12 h-12 rounded overflow-hidden border border-gray-300 dark:border-gray-700 group">
                                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                                        <button type="button" wire:click="removeAttachment({{ $index }}, 'chatAttachments')" class="absolute inset-0 bg-red-500/80 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-end bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl p-1.5 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all shadow-sm dark:shadow-inner relative z-30">

                            <label class="cursor-pointer p-2 sm:p-2.5 text-gray-400 dark:text-gray-500 hover:text-primary transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 shrink-0 mb-0.5" title="Datei anhängen">
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
                                <button type="button" @click="showEmojis = !showEmojis" class="p-2 sm:p-2.5 text-gray-400 dark:text-gray-500 hover:text-primary transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>

                                <div x-show="showEmojis" @click.outside="showEmojis = false" x-transition.opacity
                                     class="absolute bottom-[calc(100%+15px)] left-0 z-[9999] shadow-2xl rounded-2xl border border-gray-200 dark:border-gray-700 w-[280px] bg-white dark:bg-gray-900 flex flex-col overflow-hidden"
                                     style="display: none; height: 320px;">
                                    <div class="flex border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 shrink-0">
                                        <template x-for="(emojis, name) in categories" :key="name">
                                            <button type="button" @click="activeTab = name" :class="activeTab === name ? 'text-primary border-primary' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-700 dark:hover:text-gray-300'" class="flex-1 py-3 text-[10px] font-black uppercase tracking-widest transition-colors border-b-2" x-text="name"></button>
                                        </template>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-3 custom-scrollbar grid grid-cols-6 gap-2 content-start">
                                        <template x-for="emoji in categories[activeTab]" :key="emoji">
                                            <button type="button" @click="$wire.set('chatMessage', $wire.chatMessage + emoji); showEmojis = false" class="text-2xl hover:scale-125 transition-transform hover:bg-gray-100 dark:hover:bg-gray-800 rounded flex items-center justify-center aspect-square" x-text="emoji"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <textarea wire:model="chatMessage"
                                      x-data="{ resize() { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; } }"
                                      x-init="resize()" @input="resize()"
                                      @keydown.enter.prevent="if(!$event.shiftKey) { $wire.sendReply(); }"
                                      rows="1" placeholder="Schreibe eine Nachricht..."
                                      class="flex-1 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 text-sm px-2 sm:px-3 py-2.5 sm:py-3 resize-none focus:outline-none custom-scrollbar max-h-32 min-h-[44px] break-all whitespace-pre-wrap leading-relaxed cursor-pointer caret-gray-900 dark:caret-white"></textarea>

                            <button type="submit" wire:loading.attr="disabled" class="w-10 h-10 sm:w-11 sm:h-11 ml-1 sm:ml-2 rounded-full bg-primary text-gray-900 flex items-center justify-center hover:scale-110 hover:-rotate-12 transition-all duration-300 shrink-0 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)] hover:shadow-md dark:hover:shadow-[0_0_25px_rgba(197,160,89,0.8)] disabled:opacity-50 disabled:hover:scale-100 disabled:hover:rotate-0 mb-0.5 focus:outline-none">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- TICKET SCHLIESSEN MODAL --}}
    <div x-show="showCloseModal" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center">
        <div x-show="showCloseModal" x-transition.opacity @click="showCloseModal = false" class="absolute inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm"></div>
        <div x-show="showCloseModal" x-transition.scale.origin.bottom class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl p-6 sm:p-8 max-w-md w-full mx-4 overflow-hidden">
            <div class="absolute top-0 inset-x-0 h-1 bg-red-500"></div>

            <h3 class="text-xl font-black text-gray-900 dark:text-white mb-2 flex items-center gap-3">
                <span class="text-red-500 text-2xl">⚠️</span> Ticket wirklich schließen?
            </h3>

            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Damit wir unseren Service verbessern können, teile uns bitte kurz mit, warum du dieses Ticket schließt.</p>

            <form wire:submit.prevent="closeTicket">
                <div class="mb-6">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Schließgrund / Feedback *</label>
                    <textarea wire:model="closeReason" rows="3" placeholder="Hat sich das Problem von selbst gelöst? Fehlen Informationen?..." class="w-full bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white rounded-xl px-4 py-3 focus:ring-2 focus:ring-red-500 outline-none transition-colors @error('closeReason') border-2 border-red-500 @else border border-gray-200 dark:border-gray-800 @enderror"></textarea>
                    @error('closeReason') <span class="text-red-500 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="showCloseModal = false" class="flex-1 px-4 py-3 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-colors">Abbrechen</button>
                    <button type="submit" class="flex-1 px-4 py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl font-black text-xs uppercase tracking-widest shadow-md transition-all">Schließen</button>
                </div>
            </form>
        </div>
    </div>
</div>
