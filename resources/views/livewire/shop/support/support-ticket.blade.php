<div style="--theme-color: {{ $this->themeColorHex }};" class="flex flex-col lg:flex-row gap-6 lg:h-[calc(100vh-100px)] text-gray-700 dark:text-gray-300 font-sans relative"
     @admin-ticket-message-received.window="$wire.receiveMessage($event.detail)"
>
    <script type="module" src="{{ asset('vendor/emoji-picker-element/index.js') }}"></script>

    {{-- LINKE SEITE: TICKET LISTE / TABELLE --}}
    <div class="w-full {{ $viewMode === 'split' ? 'lg:w-1/3' : 'lg:w-full' }} bg-white/90 dark:bg-gray-900 rounded-[2rem] dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 flex flex-col overflow-hidden h-[60vh] lg:h-full shrink-0 transition-all duration-300">
        <div class="p-4 sm:p-6 border-b border-gray-200/80 dark:border-gray-800 shrink-0 bg-gray-50/80 dark:bg-gray-950/50">
            <h2 class="text-xl sm:text-2xl font-serif font-black text-gray-900 dark:text-white mb-4 flex items-center justify-between gap-3">
                <span class="flex items-center gap-3">
                    <span class="text-[var(--theme-color)] dark:drop-shadow-[0_0_15px_var(--theme-color)0.5)]">🛡️</span> Support Desk
                </span>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" wire:click="openCreateTicketModal" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-[var(--theme-color)] text-gray-900 shadow-sm hover:opacity-90 transition-all flex items-center gap-1.5">
                        <span>+ Nachricht an Kunde</span>
                    </button>
                    <div class="flex bg-gray-100 dark:bg-gray-950 p-1 rounded-lg border border-gray-200 dark:border-gray-800 shrink-0">
                        <button wire:click="$set('viewMode', 'split')" class="px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $viewMode === 'split' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">Split</button>
                        <button wire:click="$set('viewMode', 'table')" class="px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $viewMode === 'table' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">Tabelle</button>
                    </div>
                </div>
            </h2>

            {{-- KPIs --}}
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-2 text-center shadow-sm">
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mb-1">Offen</div>
                    <div class="text-lg font-black text-[var(--theme-color)]">{{ $kpiOpenCount }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-2 text-center shadow-sm">
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mb-1">Ø Rating</div>
                    <div class="text-lg font-black text-[var(--theme-color)]">{{ number_format($kpiAvgRating, 1, ',', '.') }} ★</div>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-2 text-center shadow-sm">
                    <div class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mb-1">Ø Zeit</div>
                    <div class="text-lg font-black text-[var(--theme-color)]">{{ $kpiAvgResolutionHrs }}h</div>
                </div>
            </div>
            <div class="flex flex-wrap sm:flex-nowrap gap-2 mb-4 sm:mb-6 bg-gray-100 dark:bg-gray-950 p-1.5 rounded-xl border border-gray-200 dark:border-gray-800 dark:shadow-inner">
                <button wire:click="$set('filterStatus', 'all')" class="flex-1 py-2 sm:py-2.5 rounded-lg text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all {{ $filterStatus === 'all' ? 'bg-white dark:bg-[var(--theme-color-20)] border border-gray-200 dark:border-[var(--theme-color)]/50 text-[var(--theme-color)] shadow-sm dark:shadow-[0_0_15px_var(--theme-color-20)]' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white border border-transparent' }}">Alle</button>
                <button wire:click="$set('filterStatus', 'open')" class="flex-1 py-2 sm:py-2.5 rounded-lg text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all {{ $filterStatus === 'open' ? 'bg-white dark:bg-[var(--theme-color-20)] border border-gray-200 dark:border-[var(--theme-color)]/50 text-[var(--theme-color)] shadow-sm dark:shadow-[0_0_15px_var(--theme-color-20)]' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white border border-transparent' }}">Offen</button>
                <button wire:click="$set('filterStatus', 'answered')" class="flex-1 py-2 sm:py-2.5 rounded-lg text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all {{ $filterStatus === 'answered' ? 'bg-white dark:bg-[var(--theme-color-20)] border border-gray-200 dark:border-[var(--theme-color)]/50 text-[var(--theme-color)] shadow-sm dark:shadow-[0_0_15px_var(--theme-color-20)]' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white border border-transparent' }}">Antwort</button>
                <button wire:click="$set('filterStatus', 'closed')" class="flex-1 py-2 sm:py-2.5 rounded-lg text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all {{ $filterStatus === 'closed' ? 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white border border-transparent' }}">Zu</button>
            </div>
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suchen (ID, Name...)" class="w-full bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl pl-10 pr-4 py-2.5 sm:py-3 text-xs sm:text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[var(--theme-color)] outline-none transition-all dark:shadow-inner placeholder-gray-400 dark:placeholder-gray-600">
            </div>
        </div>

        @if($viewMode === 'split')
            <div class="flex-1 overflow-y-auto custom-scrollbar bg-white/60 dark:bg-gray-900" x-data>
                @forelse($tickets as $t)
                    @php $unread = $t->messages->where('sender_type', 'customer')->where('is_read_by_admin', false)->count(); @endphp
                    <div wire:click="selectTicket('{{ $t->id }}')" @click="window.dispatchEvent(new CustomEvent('clear-admin-ticket-badge')); if(window.innerWidth < 1024) { setTimeout(() => document.getElementById('ticket-chat-area').scrollIntoView({behavior: 'smooth'}), 100); }" class="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-800/50 cursor-pointer transition-all {{ $activeTicketId === $t->id ? 'bg-[var(--theme-color)]/10 border-l-4 border-l-[var(--theme-color)] dark:shadow-inner' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50 border-l-4 border-l-transparent' }}">
                        <div class="flex justify-between items-start mb-2 gap-2">
                            <div class="flex items-center gap-2">
                                @if($unread > 0) <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_rgba(239,68,68,0.8)] shrink-0"></span> @endif
                                <div x-data="{ copied: false }" class="inline-flex items-center gap-1.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-[var(--theme-color)]/30 px-2 py-0.5 rounded dark:shadow-inner group/copy">
                                    <span class="text-[10px] sm:text-xs font-mono font-black text-[var(--theme-color)]">{{ $t->ticket_number }}</span>
                                </div>
                            </div>
                            <span class="text-[9px] sm:text-[10px] font-bold text-gray-500 uppercase tracking-wider shrink-0 text-right">{{ $t->updated_at->diffForHumans() }}</span>
                        </div>
                        <h3 class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white truncate mb-1">{{ $t->subject }}</h3>
                        <p class="text-[10px] sm:text-xs text-gray-600 dark:text-gray-400 truncate flex items-center gap-1.5">
                            {{ $t->customer->first_name }} {{ $t->customer->last_name }}
                        </p>
                    </div>
                @empty
                    <div class="p-8 sm:p-12 text-center text-gray-500 text-xs sm:text-sm font-bold">Keine Tickets gefunden.</div>
                @endforelse
                
                @if($tickets->hasPages())
                    <div class="p-4 border-t border-gray-200/80 dark:border-gray-800">
                        {{ $tickets->links('pagination::tailwind') }}
                    </div>
                @endif
            </div>
        @else
            <div class="flex-1 overflow-x-auto overflow-y-auto custom-scrollbar bg-white/60 dark:bg-gray-900">
                <table class="w-full text-left text-xs sm:text-sm text-gray-700 dark:text-gray-300">
                    <thead class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50/90 dark:bg-gray-950/50 sticky top-0 z-10 w-full border-b border-gray-200/80 dark:border-gray-800">
                        <tr>
                            <th class="px-4 xl:px-6 py-4 whitespace-nowrap">ID / Status</th>
                            <th class="px-4 xl:px-6 py-4">Kunde</th>
                            <th class="px-4 xl:px-6 py-4">Betreff</th>
                            <th class="px-4 xl:px-6 py-4 text-center">Bewertung</th>
                            <th class="px-4 xl:px-6 py-4 whitespace-nowrap">Letztes Update</th>
                            <th class="px-4 xl:px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/50">
                        @forelse($tickets as $t)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ $activeTicketId === $t->id ? 'bg-[var(--theme-color)]/10' : '' }}">
                            <td class="px-4 xl:px-6 py-3 whitespace-nowrap">
                                <div class="font-mono font-black text-[var(--theme-color)] text-xs mb-1">{{ $t->ticket_number }}</div>
                                <span class="px-2 py-0.5 rounded text-[9px] uppercase font-bold tracking-widest {{ $t->status === 'open' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30' : ($t->status === 'answered' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-600') }}">
                                    {{ $t->status }}
                                </span>
                            </td>
                            <td class="px-4 xl:px-6 py-3 min-w-[200px]">
                                <div class="font-bold text-gray-900 dark:text-white text-sm">{{ $t->customer->first_name }} {{ $t->customer->last_name }}</div>
                                <div class="text-[10px] text-gray-500">{{ $t->customer->email }}</div>
                            </td>
                            <td class="px-4 xl:px-6 py-3 min-w-[250px]">
                                <div class="font-bold text-gray-800 dark:text-gray-200 line-clamp-2 text-sm">{{ $t->subject }}</div>
                                @if($t->close_reason)
                                    <div class="mt-1.5 text-[10px] text-gray-600 dark:text-gray-400 border-l-2 border-red-500/50 pl-2">
                                        <span class="font-bold text-gray-500 block uppercase tracking-widest text-[9px] mb-0.5">Schließgrund</span> 
                                        {{ $t->close_reason }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 xl:px-6 py-3 text-center min-w-[200px]">
                                @if($t->status === 'closed' && $t->rating)
                                    <div class="flex flex-col items-center">
                                        <div class="text-amber-500 text-sm font-bold mb-1" title="{{ $t->rating }} Sterne">
                                            {{ str_repeat('★', $t->rating) }}{{ str_repeat('☆', 5 - $t->rating) }}
                                        </div>
                                        @if($t->feedback_text)
                                            <p class="text-[10px] text-gray-600 dark:text-gray-400 italic bg-gray-50 dark:bg-gray-950 p-1.5 rounded border border-gray-200 dark:border-gray-800 line-clamp-2 w-48 text-left" title="{{ $t->feedback_text }}">"{{ $t->feedback_text }}"</p>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-600 text-xs">-</span>
                                @endif
                            </td>
                            <td class="px-4 xl:px-6 py-3 text-[10px] text-gray-500 font-bold whitespace-nowrap">
                                {{ $t->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-4 xl:px-6 py-3 text-right">
                                 <button wire:click="selectTicket('{{ $t->id }}'); $set('viewMode', 'split')" @click="window.dispatchEvent(new CustomEvent('clear-admin-ticket-badge'))" class="px-4 py-2 bg-[var(--theme-color)] text-gray-900 font-black rounded-xl tracking-widest uppercase text-[10px] hover:scale-105 transition-transform dark:shadow-[0_0_10px_var(--theme-color)0.3)]">Öffnen</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500 font-bold text-sm">Keine Tickets gefunden.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($tickets->hasPages())
                    <div class="p-4 border-t border-gray-200/80 dark:border-gray-800 sticky left-0">
                        {{ $tickets->links('pagination::tailwind') }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if($viewMode === 'split')
        {{-- RECHTE SEITE: CHAT / DETAIL ANSICHT --}}
        <div id="ticket-chat-area" class="w-full lg:flex-1 bg-white/90 dark:bg-gray-900 rounded-[2rem] dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 flex flex-col overflow-hidden relative min-h-[70vh] lg:h-full lg:min-h-0 transition-opacity duration-300">

        @if($isCreatingTicket)
            {{-- ADMIN-INITIATED TICKET / DIREKTNACHRICHT AN KUNDEN --}}
            <div class="p-4 sm:p-6 border-b border-gray-200/80 dark:border-gray-800 shrink-0 flex items-center justify-between bg-gray-50/80 dark:bg-gray-950/50">
                <div class="flex items-center gap-2.5">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">Direktnachricht</span>
                    <h2 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white">Neue Nachricht an Kunden senden</h2>
                    <div x-data="{ tip: false }" class="relative inline-flex items-center">
                        <button type="button" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = !tip" class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-[10px] font-black flex items-center justify-center hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors">!</button>
                        <div x-show="tip" x-cloak class="absolute left-6 top-0 z-30 w-72 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">
                            Erstellt ein direktes Support-Ticket für den ausgewählten Kunden. Der Kunde sieht sofort den roten Benachrichtigungspunkt im Kundenportal und erhält bei Offline-Status eine E-Mail-Benachrichtigung.
                        </div>
                    </div>
                </div>
                <button type="button" wire:click="closeCreateTicketModal" class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-red-500 transition-all">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit.prevent="createAdminTicket" class="flex-1 overflow-y-auto p-6 sm:p-8 space-y-6 custom-scrollbar">
                {{-- 1. Kunde auswählen --}}
                <div class="space-y-2 relative">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Empfänger (Kunde) *</label>
                    @if($this->selectedNewTicketCustomer)
                        @php
                            $selCust = $this->selectedNewTicketCustomer;
                            $selInitials = mb_strtoupper(mb_substr((string) $selCust->first_name, 0, 1) . mb_substr((string) $selCust->last_name, 0, 1));
                            $selShortId = 'KDN-' . strtoupper(substr(str_replace('-', '', (string) $selCust->id), 0, 6));
                        @endphp
                        <div class="flex items-center justify-between gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-[var(--theme-color)]/40">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] text-[var(--theme-color)] font-black text-xs flex items-center justify-center shrink-0">
                                    {{ $selInitials !== '' ? $selInitials : 'KD' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">
                                            {{ $selCust->first_name }} {{ $selCust->last_name }}
                                        </p>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400">{{ $selShortId }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $selCust->email }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="clearSelectedCustomerForNewTicket" class="px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:text-red-500 transition-all shrink-0">
                                Ändern
                            </button>
                        </div>
                    @else
                        @php
                            $searchResults = $this->customerSearchResults;
                            $hasTypedQuery = mb_strlen(trim((string) $newTicketCustomerSearch)) >= 1;
                        @endphp
                        <div class="relative">
                            <div class="relative flex items-center">
                                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text"
                                       wire:model.live.debounce.150ms="newTicketCustomerSearch"
                                       autocomplete="off"
                                       placeholder="Kundenname, E-Mail oder ID tippen..."
                                       class="w-full text-sm border border-gray-200 dark:border-gray-800 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white pl-11 pr-10 py-3.5 outline-none focus:ring-2 focus:ring-[var(--theme-color)] transition-all">
                                <div wire:loading wire:target="newTicketCustomerSearch" class="absolute right-3.5">
                                    <svg class="animate-spin h-4 w-4 text-[var(--theme-color)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                </div>
                                @if($hasTypedQuery)
                                    <button type="button" wire:loading.remove wire:target="newTicketCustomerSearch" wire:click="$set('newTicketCustomerSearch', '')" class="absolute right-3.5 text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </div>

                            @if($hasTypedQuery)
                                <div class="mt-2 rounded-2xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 shadow-xl overflow-hidden z-20">
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900/90 border-b border-gray-200/80 dark:border-gray-800 flex items-center justify-between gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                        <span>{{ $searchResults['total'] }} {{ $searchResults['total'] === 1 ? 'Treffer' : 'Treffer' }}</span>
                                        @if($searchResults['has_more'])
                                            <span class="text-[var(--theme-color)]">Top 15 von {{ $searchResults['total'] }} Kunden</span>
                                        @endif
                                    </div>
                                    <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/70 custom-scrollbar">
                                        @forelse($searchResults['items'] as $entry)
                                            <button type="button"
                                                    wire:key="cust-search-{{ $entry['id'] }}"
                                                    wire:click="selectCustomerForNewTicket('{{ $entry['id'] }}')"
                                                    class="w-full text-left px-4 py-3 flex items-center justify-between gap-3 hover:bg-[var(--theme-color-10)] transition-colors group">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <div class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-gray-900 group-hover:bg-[var(--theme-color-20)] text-gray-700 dark:text-gray-300 group-hover:text-[var(--theme-color)] font-black text-xs flex items-center justify-center shrink-0 transition-colors">
                                                        {{ $entry['initials'] }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <span class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white truncate">{{ $entry['full_name'] }}</span>
                                                            @if($entry['is_similar'])
                                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Ähnlich</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                                            <span class="truncate">{{ $entry['email'] }}</span>
                                                            @if(!empty($entry['city']))
                                                                <span class="text-gray-300 dark:text-gray-700">•</span>
                                                                <span class="truncate">{{ $entry['city'] }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400">{{ $entry['short_id'] }}</span>
                                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-[var(--theme-color)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                </div>
                                            </button>
                                        @empty
                                            <div class="p-4 text-center text-xs text-gray-500 dark:text-gray-400 font-bold">
                                                Kein passender oder ähnlicher Kunde gefunden.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                    @error('newTicketCustomerId') <span class="text-xs text-red-500 font-bold block">{{ $message }}</span> @enderror
                </div>

                {{-- 2. Kategorie & optionale Bestellung --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Kategorie</label>
                        <select wire:model="newTicketCategory" class="w-full text-sm border border-gray-200 dark:border-gray-800 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white p-3.5 outline-none focus:ring-2 focus:ring-[var(--theme-color)]">
                            <option value="support">Hilfe &amp; Support</option>
                            <option value="general">Allgemeine Frage</option>
                            <option value="return">Retoure / Reklamation</option>
                            <option value="bug">Systemhinweis</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Verknüpfte Bestellung (Optional)</label>
                        <select wire:model="newTicketOrderId" class="w-full text-sm border border-gray-200 dark:border-gray-800 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white p-3.5 outline-none focus:ring-2 focus:ring-[var(--theme-color)]">
                            <option value="">Ohne Bestellbezug</option>
                            @foreach($this->selectedCustomerOrders as $cOrd)
                                <option value="{{ $cOrd->id }}">Bestellung #{{ $cOrd->order_number }} ({{ $cOrd->created_at?->format('d.m.Y') }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- 3. Betreff --}}
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Betreff *</label>
                    <input type="text"
                           wire:model="newTicketSubject"
                           placeholder="z. B. Rückfrage zu deiner Gravur / Wichtige Information zu deiner Bestellung"
                           class="w-full text-sm border border-gray-200 dark:border-gray-800 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white p-3.5 outline-none focus:ring-2 focus:ring-[var(--theme-color)]">
                    @error('newTicketSubject') <span class="text-xs text-red-500 font-bold block">{{ $message }}</span> @enderror
                </div>

                {{-- 4. Nachricht --}}
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Nachricht an Kunden *</label>
                    <textarea wire:model="newTicketMessage"
                              rows="5"
                              placeholder="Schreibe deine Nachricht an den Kunden..."
                              class="w-full text-sm border border-gray-200 dark:border-gray-800 rounded-2xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white p-4 outline-none focus:ring-2 focus:ring-[var(--theme-color)] resize-none"></textarea>
                    @error('newTicketMessage') <span class="text-xs text-red-500 font-bold block">{{ $message }}</span> @enderror
                </div>

                {{-- 5. Anhänge & Absenden --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2 border-t border-gray-200 dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-600 dark:text-gray-300 hover:border-[var(--theme-color)] cursor-pointer transition-all">
                            <svg class="w-4 h-4 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            <span>Anhang hinzufügen</span>
                            <input type="file" wire:model="newTicketAttachments" multiple class="hidden">
                        </label>
                        @if(!empty($newTicketAttachments))
                            <span class="text-xs font-bold text-[var(--theme-color)]">{{ count($newTicketAttachments) }} Datei(en) ausgewählt</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="closeCreateTicketModal" class="px-5 py-3 rounded-xl text-xs font-black uppercase tracking-widest bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-all">
                            Abbrechen
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest bg-[var(--theme-color)] text-gray-900 shadow-sm hover:opacity-90 transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>Nachricht senden</span>
                        </button>
                    </div>
                </div>
            </form>
        @elseif($activeTicket)
            <div class="p-4 sm:p-6 border-b border-gray-200/80 dark:border-gray-800 shrink-0 flex flex-col sm:flex-row justify-between items-start sm:items-center bg-gray-50/80 dark:bg-gray-950/50 relative z-10 gap-4">
                <div class="w-full sm:w-auto min-w-0">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-[10px] sm:text-xs text-[var(--theme-color)] font-mono font-black tracking-widest bg-white dark:bg-gray-900 border border-gray-200 dark:border-[var(--theme-color)]/50 px-2 sm:px-3 py-1 rounded-lg dark:shadow-inner">{{ $activeTicket->ticket_number }}</span>
                        @if($activeTicket->status === 'closed')
                            <span class="text-[9px] text-red-500 dark:text-red-400 font-black uppercase tracking-widest bg-red-100 dark:bg-red-900/30 px-2 py-0.5 rounded border border-red-200 dark:border-red-500/30 shrink-0">Geschlossen</span>
                        @endif
                    </div>
                    <h2 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white mb-2 leading-tight break-words whitespace-pre-wrap">{{ $activeTicket->subject }}</h2>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 flex items-center gap-2">
                        <span class="text-[var(--theme-color)]">{{ $activeTicket->customer->first_name }} {{ $activeTicket->customer->last_name }}</span>
                    </p>
                </div>
                <div class="shrink-0">
                    @if($activeTicket->status !== 'closed')
                        <button wire:click="closeTicket" class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-400 hover:text-white hover:bg-red-500 border border-gray-200 dark:border-gray-700 hover:border-red-500 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm">SupportTicket schließen</button>
                    @endif
                </div>
            </div>

            @if($activeTicket->order)
                <div class="bg-blue-50 dark:bg-blue-900/10 border-b border-blue-200 dark:border-blue-900/30 p-3 sm:p-4 px-4 sm:px-6 flex items-center justify-between shrink-0 relative z-10 dark:shadow-inner">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-500/20 flex items-center justify-center shrink-0"><span class="text-blue-600 dark:text-blue-400 text-sm">📦</span></div>
                        <div class="min-w-0">
                            <p class="text-[8px] sm:text-[9px] text-blue-600 dark:text-blue-400 font-black uppercase tracking-widest mb-0.5">Verknüpfte Bestellung</p>
                            <a href="/admin/orders?search={{ $activeTicket->order->order_number }}" target="_blank" class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate underline decoration-blue-500/50 underline-offset-2">Bestellnr: {{ $activeTicket->order->order_number }}</a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- AUTO-SCROLL FIX: Nutzt AlpineJS $nextTick für perfektes Scrollen --}}
            <div class="flex-1 overflow-y-auto p-4 sm:p-8 space-y-6 custom-scrollbar bg-gray-50/40 dark:bg-gray-900/50" id="chat-container"
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
                        <div class="flex justify-start w-full">
                            <div class="max-w-[90%] sm:max-w-[80%] xl:max-w-[70%]">
                                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl rounded-tl-sm p-4 sm:p-5 shadow-sm dark:shadow-lg text-gray-900 dark:text-gray-200">
                                    <p class="{{ $isOnlyEmojis ? 'text-5xl py-2' : 'text-sm' }} leading-relaxed break-words whitespace-pre-wrap">{{ $msg->message }}</p>
                                    @if($msg->attachments)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($msg->attachments as $img)
                                                <a href="{{ asset('storage/' . $img) }}" target="_blank" class="block w-16 h-16 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 hover:opacity-80 transition-opacity"><img src="{{ asset('storage/' . $img) }}" class="w-full h-full object-cover"></a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <p class="text-[9px] sm:text-[10px] text-gray-500 font-bold mt-2 px-1">{{ $activeTicket->customer->first_name }} {{ $activeTicket->customer->last_name }} • {{ $msg->created_at->format('H:i') }}</p>
                            </div>
                        </div>
                    @else
                        <div class="flex justify-end w-full">
                            <div class="max-w-[90%] sm:max-w-[80%] xl:max-w-[70%]">
                                <div class="bg-[var(--theme-color-15)] border border-[var(--theme-color)]/30 rounded-2xl rounded-tr-sm p-4 sm:p-5 shadow-sm dark:shadow-lg text-gray-900 dark:text-white">
                                    <p class="{{ $isOnlyEmojis ? 'text-5xl py-2' : 'text-sm' }} leading-relaxed break-words whitespace-pre-wrap">{{ $msg->message }}</p>
                                    @if($msg->attachments)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($msg->attachments as $img)
                                                <a href="{{ asset('storage/' . $img) }}" target="_blank" class="block w-16 h-16 rounded-lg overflow-hidden border border-[var(--theme-color)]/50 hover:opacity-80 transition-opacity"><img src="{{ asset('storage/' . $img) }}" class="w-full h-full object-cover"></a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex justify-end items-center gap-2 mt-2 px-1">
                                    <p class="text-[9px] sm:text-[10px] text-gray-500 font-bold">Seelen-Admin • {{ $msg->created_at->format('H:i') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
                
                @if($activeTicket->status === 'closed' && $activeTicket->close_reason)
                    <div class="flex justify-center w-full mt-4">
                        <div class="max-w-[90%] sm:max-w-[70%] text-center">
                            <div class="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 rounded-xl p-3 dark:shadow-inner inline-block">
                                <p class="text-[9px] font-black uppercase tracking-widest text-red-500/80 mb-1">Angegebener Schließgrund</p>
                                <p class="text-xs sm:text-sm text-red-700 dark:text-red-200 italic font-medium">"{{ $activeTicket->close_reason }}"</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- INPUT BEREICH --}}
            <div class="p-4 sm:p-6 border-t border-gray-200/80 dark:border-gray-800 shrink-0 bg-gray-50/90 dark:bg-gray-950/80 relative z-20">
                @if($activeTicket->status === 'closed')
                    <div class="text-center py-3 sm:py-4 bg-gray-100 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 dark:shadow-inner">
                        <p class="text-gray-500 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.2em]">Dieses SupportTicket ist geschlossen</p>
                    </div>
                @else
                    <form wire:submit.prevent="sendReply" class="w-full relative">

                        @if($replyAttachments)
                            <div class="mb-3 flex flex-wrap gap-2 bg-white dark:bg-gray-900 p-2 rounded-xl border border-gray-200 dark:border-gray-800 dark:shadow-inner">
                                @foreach($replyAttachments as $index => $photo)
                                    <div class="relative w-12 h-12 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 group">
                                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                                        <button type="button" wire:click="removeAttachment({{ $index }})" class="absolute inset-0 bg-red-500/80 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-end bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl p-1.5 focus-within:border-[var(--theme-color)] focus-within:ring-1 focus-within:ring-[var(--theme-color)] transition-all shadow-sm dark:shadow-inner relative z-30">

                            <label class="cursor-pointer p-2 sm:p-2.5 text-gray-400 hover:text-[var(--theme-color)] transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 shrink-0 mb-0.5">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <input type="file" wire:model="replyAttachments" multiple class="hidden">
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
                                <button type="button" @click="showEmojis = !showEmojis" class="p-2 sm:p-2.5 text-gray-400 hover:text-[var(--theme-color)] transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>

                                <div x-show="showEmojis" @click.outside="showEmojis = false" x-transition.opacity
                                     class="absolute bottom-[calc(100%+15px)] left-0 z-[9999] shadow-xl dark:shadow-[0_0_30px_rgba(0,0,0,0.8)] rounded-2xl border border-gray-200 dark:border-gray-700 w-[280px] bg-white dark:bg-gray-900 flex flex-col overflow-hidden"
                                     style="display: none; height: 320px;">
                                    <div class="flex border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 shrink-0">
                                        <template x-for="(emojis, name) in categories" :key="name">
                                            <button type="button" @click="activeTab = name" :class="activeTab === name ? 'text-[var(--theme-color)] border-[var(--theme-color)]' : 'text-gray-500 border-transparent hover:text-gray-700 dark:hover:text-gray-300'" class="flex-1 py-3 text-[10px] font-black uppercase tracking-widest transition-colors border-b-2" x-text="name"></button>
                                        </template>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-3 custom-scrollbar grid grid-cols-6 gap-2 content-start">
                                        <template x-for="emoji in categories[activeTab]" :key="emoji">
                                            <button type="button" @click="$wire.set('replyMessage', $wire.replyMessage + emoji); showEmojis = false" class="text-2xl hover:scale-125 transition-transform hover:bg-gray-100 dark:hover:bg-gray-800 rounded flex items-center justify-center aspect-square" x-text="emoji"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <textarea wire:model="replyMessage"
                                      x-data="{ resize() { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; } }"
                                      x-init="resize()" @input="resize()"
                                      @keydown.enter.prevent="if(!$event.shiftKey) { $wire.sendReply(); }"
                                      rows="1" placeholder="Schreibe eine Antwort..."
                                      class="flex-1 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 text-sm px-2 sm:px-3 py-2.5 sm:py-3 resize-none focus:outline-none custom-scrollbar max-h-32 min-h-[44px] break-words whitespace-pre-wrap leading-relaxed"></textarea>

                            <button type="submit" wire:loading.attr="disabled" class="w-10 h-10 sm:w-11 sm:h-11 ml-1 sm:ml-2 rounded-full bg-[var(--theme-color)] text-gray-900 flex items-center justify-center hover:scale-110 hover:-rotate-12 transition-all duration-300 shrink-0 shadow-sm dark:shadow-[0_0_15px_var(--theme-color)0.3)] dark:hover:shadow-[0_0_25px_var(--theme-color)0.8)] disabled:opacity-50 disabled:hover:scale-100 disabled:hover:rotate-0 mb-0.5 focus:outline-none">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @else
            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center mb-4 text-[var(--theme-color)]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <p class="text-sm font-bold text-gray-900 dark:text-white mb-4">Wähle links ein Ticket aus oder starte eine Direktnachricht</p>
                <button type="button" wire:click="openCreateTicketModal" class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest bg-[var(--theme-color)] text-gray-900 shadow-sm hover:opacity-90 transition-all">
                    + Nachricht an Kunde
                </button>
            </div>
        @endif
    </div>
    @endif
</div>
