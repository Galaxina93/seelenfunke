<div>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-white font-bold">Kontaktanfragen Inbox</h1>
                <p class="mt-1 text-sm text-gray-400">Zentrale Verwaltung aller formellen Kundenanfragen aus dem Frontend-Kontaktformular.</p>
            </div>
            <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Suche..." class="block w-full rounded-xl bg-gray-900 border border-gray-700 py-2 pl-10 text-gray-200 placeholder-gray-500 focus:ring-[#C5A059] focus:border-[#C5A059] sm:text-sm shadow-inner transition-colors">
                </div>
                <select wire:model.live="statusFilter" class="block w-full rounded-xl bg-gray-900 border border-gray-700 py-2 pl-3 pr-10 text-gray-200 focus:ring-[#C5A059] focus:border-[#C5A059] sm:text-sm shadow-inner transition-colors">
                    <option value="">Alle Status</option>
                    <option value="new">Neu</option>
                    <option value="in_progress">In Bearbeitung</option>
                    <option value="waiting_for_customer">Wartet auf Kunde</option>
                    <option value="resolved">Erledigt</option>
                </select>
            </div>
        </div>

        <div class="bg-gray-800 shadow-lg border border-gray-700/50 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700/50">
                    <thead class="bg-gray-900/50 text-xs font-bold text-gray-400 uppercase tracking-wider text-left border-b border-gray-700/50">
                        <tr>
                            <th class="px-6 py-4 w-32">Ticket-ID</th>
                            <th class="px-3 py-4">Kontakt</th>
                            <th class="px-4 py-4 w-1/4">Thema & Kategorie</th>
                            <th class="px-3 py-4 w-40">Status</th>
                            <th class="px-3 py-4 w-32">Datum</th>
                            <th class="px-6 py-4 text-right w-44">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50 bg-gray-800">
                        @forelse($requests as $req)
                            <tr class="hover:bg-gray-700/20 transition-colors {{ $req->status === 'new' ? 'bg-amber-500/5' : '' }} {{ $selectedRequestId === $req->id ? 'bg-gray-700/40 border-b border-gray-700/50' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-200 border-l-4 {{ $req->status==='new' ? 'border-amber-400' : ($selectedRequestId === $req->id ? 'border-[#C5A059]' : 'border-transparent') }}">
                                    {{ $req->ticket_number }}
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-200">{{ $req->first_name }} {{ $req->last_name }}</div>
                                    <div class="text-[12px] text-gray-400"><a href="mailto:{{ $req->email }}" class="hover:text-[#C5A059] transition-colors">{{ $req->email }}</a></div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-[13px] font-medium text-gray-300 truncate max-w-[200px] xl:max-w-[280px]" title="{{ $req->subject }}">{{ $req->subject }}</div>
                                    <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">{{ $req->category ?? 'Allgemein' }}</div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <select wire:change="updateStatus('{{ $req->id }}', $event.target.value)" class="text-xs font-bold rounded-lg border focus:ring-0 focus:outline-none transition-colors px-2.5 py-1.5 cursor-pointer 
                                        {{ $req->status === 'new' ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : '' }}
                                        {{ $req->status === 'in_progress' ? 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' : '' }}
                                        {{ $req->status === 'waiting_for_customer' ? 'bg-purple-500/10 text-purple-400 border-purple-500/30' : '' }}
                                        {{ $req->status === 'resolved' ? 'bg-green-500/10 text-green-400 border-green-500/30' : '' }}
                                    ">
                                        <option value="new" class="bg-gray-800 text-gray-200" {{ $req->status === 'new' ? 'selected' : '' }}>Neu</option>
                                        <option value="in_progress" class="bg-gray-800 text-gray-200" {{ $req->status === 'in_progress' ? 'selected' : '' }}>In Bearbeitung</option>
                                        <option value="waiting_for_customer" class="bg-gray-800 text-gray-200" {{ $req->status === 'waiting_for_customer' ? 'selected' : '' }}>Wartet auf Kunde</option>
                                        <option value="resolved" class="bg-gray-800 text-gray-200" {{ $req->status === 'resolved' ? 'selected' : '' }}>Erledigt</option>
                                    </select>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-xs font-medium text-gray-400">
                                    {{ $req->created_at->format('d.m.Y - H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button wire:click="openRequest('{{ $req->id }}')" class="inline-flex items-center justify-center min-w-[145px] gap-2 px-3 py-1.5 {{ $selectedRequestId === $req->id ? 'bg-[#C5A059] text-gray-900 border-[#C5A059]' : 'bg-gray-900 text-[#C5A059] border-[#C5A059]/50 hover:bg-[#C5A059] hover:text-white' }} border text-xs font-bold rounded-lg transition-all shadow-sm focus:outline-none">
                                        @if($selectedRequestId === $req->id)
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                            Schließen
                                        @else
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                            Ansehen & Antworten
                                        @endif
                                    </button>
                                </td>
                            </tr>
                            
                            {{-- INLINE ACCORDION: Chat & Editor --}}
                            @if($selectedRequestId === $req->id && $selectedRequest)
                                <tr>
                                    <td colspan="6" class="p-0 border-b border-gray-700 bg-gray-900/40">
                                        <div class="animate-[fadeIn_0.15s_ease-out] border-l-4 border-l-[#C5A059] grid grid-cols-1 lg:grid-cols-5 2xl:grid-cols-3">
                                            
                                            <!-- Chat History -->
                                            <div class="lg:col-span-3 2xl:col-span-2 px-6 py-5 max-h-[380px] overflow-y-auto border-r border-gray-700 custom-scrollbar">
                                                <div class="space-y-4">
                                                    @foreach($selectedRequest->messages as $msg)
                                                        @if($msg->sender_type === 'customer')
                                                            <div class="flex flex-col items-start max-w-[90%]">
                                                                <div class="flex items-center space-x-2 mb-1 ml-1 opacity-90">
                                                                    <div class="w-6 h-6 bg-cyan-600/30 border border-cyan-500/40 rounded-full flex items-center justify-center shadow-inner text-[9px] font-bold text-cyan-200 uppercase tracking-wider">{{ substr($selectedRequest->first_name, 0, 1) }}{{ substr($selectedRequest->last_name, 0, 1) }}</div>
                                                                    <span class="text-xs font-bold text-gray-200">{{ $selectedRequest->first_name }}</span>
                                                                    <span class="text-[10px] font-medium text-gray-500">&bull; {{ $msg->created_at->format('d.m.y - H:i') }}</span>
                                                                </div>
                                                                <div class="bg-gray-800/80 border border-gray-700/50 shadow-sm rounded-xl rounded-tl-sm px-4 py-3 text-[13px] text-gray-300 whitespace-pre-wrap ml-7 leading-relaxed">{{ $msg->message }}</div>
                                                            </div>
                                                        @else
                                                            <div class="flex flex-col items-end max-w-[90%] ml-auto">
                                                                <div class="flex items-center space-x-2 mb-1 justify-end mr-1 opacity-90">
                                                                    <span class="text-[10px] font-medium text-gray-500">{{ $msg->created_at->format('d.m.y - H:i') }} &bull;</span>
                                                                    <span class="text-[11px] font-bold text-[#C5A059]">Support</span>
                                                                    <div class="w-6 h-6 bg-gradient-to-r from-[#C5A059] to-[#D6B778] rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-md border border-[#D6B778]/50">
                                                                        S
                                                                    </div>
                                                                </div>
                                                                <div class="bg-gradient-to-br from-[#1c160b] to-[#2a2212] border border-[#C5A059]/30 shadow-md rounded-xl rounded-tr-sm px-4 py-3 text-[13px] text-gray-200 whitespace-pre-wrap mr-7 leading-relaxed">{{ $msg->message }}</div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>

                                            <!-- Editor Region -->
                                            <div class="lg:col-span-2 2xl:col-span-1 px-6 py-5 flex flex-col bg-gray-800/50">
                                                <form wire:submit.prevent="sendReply" class="flex flex-col h-full">
                                                    <div class="flex justify-between items-center mb-2.5">
                                                        <label class="block text-xs font-bold text-gray-300">Antwort verfassen</label>
                                                        <div class="flex gap-1.5">
                                                            <button type="button" wire:click="insertCannedResponse('busy')" title="Hohes Aufkommen" class="p-1 rounded bg-gray-700 text-gray-400 hover:text-white hover:bg-gray-600 transition-colors text-xs border border-gray-600">⏱️</button>
                                                            <button type="button" wire:click="insertCannedResponse('details')" title="Details anfragen" class="p-1 rounded bg-gray-700 text-gray-400 hover:text-white hover:bg-gray-600 transition-colors text-xs border border-gray-600">❓</button>
                                                            <button type="button" wire:click="insertCannedResponse('calculator')" title="Kalkulator" class="p-1 rounded bg-gray-700 text-gray-400 hover:text-white hover:bg-gray-600 transition-colors text-xs border border-gray-600">💲</button>
                                                        </div>
                                                    </div>
                                                    
                                                    <textarea wire:model="replyMessage" class="block w-full flex-grow min-h-[140px] resize-none rounded-xl bg-gray-900 border border-gray-700 py-3 px-4 text-[13px] text-gray-200 shadow-inner focus:ring-1 focus:border-[#C5A059] focus:ring-[#C5A059]/50 transition-colors" placeholder="Nachricht an {{ $selectedRequest->first_name }}..."></textarea>
                                                    
                                                    <div class="mt-4 flex flex-col xl:flex-row items-center justify-between gap-3">
                                                        <p class="text-[10px] text-gray-500 leading-tight w-full xl:max-w-[140px]">Wird per E-Mail an <strong class="text-gray-400 truncate block">{{ $selectedRequest->email }}</strong> gesendet.</p>
                                                        
                                                        <div class="flex-1 flex justify-center">
                                                            @error('replyMessage')
                                                                <span class="text-[11px] font-bold text-red-400 bg-red-400/10 border border-red-500/20 px-3 py-1.5 rounded-lg animate-pulse">{{ $message }}</span>
                                                            @enderror
                                                        </div>

                                                        <button type="submit" 
                                                                x-data="{ saved: false, error: false }" 
                                                                x-on:saved-reply.window="saved = true; setTimeout(() => saved = false, 2500)"
                                                                x-on:error-reply.window="error = true; setTimeout(() => error = false, 4000)"
                                                                class="inline-flex w-full xl:w-auto items-center justify-center gap-2 rounded-xl bg-[#C5A059] px-4 py-2 text-xs font-bold text-gray-900 hover:bg-[#D6B778] transition-colors shadow-sm focus:outline-none">
                                                            <span x-show="!saved && !error">Senden & Speichern</span>
                                                            <span x-show="saved" style="display: none;" class="text-green-900">Gesendet! ✓</span>
                                                            <span x-show="error" style="display: none;" class="text-red-900">Fehler! ×</span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                            
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <h3 class="text-sm font-bold text-gray-400">Keine Anfragen gefunden</h3>
                                    <p class="mt-1 text-sm text-gray-500">Es gibt derzeit keine Tickets, die deinen Kriterien entsprechen.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 bg-gray-900/30 border-t border-gray-700/50">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</div>
