<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-xl text-cyan-500 leading-tight flex items-center gap-2">
                <x-heroicon-o-chat-bubble-left-ellipsis class="w-6 h-6" />
                Live Support Analytics
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

            {{-- 1. KPIs --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
                <!-- KPI 1 -->
                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-red-400 mb-1 flex items-center gap-1" title="Anzahl der Chats, in denen die KI nicht abschließend weiterwusste und manuell an einen Mitarbeiter eskaliert hat (needs_employee).">Eskalationsstufe (KI Limit) <x-heroicon-o-information-circle class="w-4 h-4 opacity-75 cursor-help" /></p>
                        <h3 class="text-3xl font-black text-white">{{ $needsEmployeeCount }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-red-400/20 flex items-center justify-center text-red-500 ring-1 ring-red-400/30">
                        <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                    </div>
                </div>

                <!-- KPI 2 -->
                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-cyan-400 mb-1 flex items-center gap-1" title="Gesamtzahl der Support-Chats, die von der KI aktuell live und aktiv betreut werden.">Aktive KI-Chats (Offen) <x-heroicon-o-information-circle class="w-4 h-4 opacity-75 cursor-help" /></p>
                        <h3 class="text-3xl font-black text-white">{{ $openCount }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-cyan-400/20 flex items-center justify-center text-cyan-500 ring-1 ring-cyan-400/30">
                        <x-heroicon-o-chat-bubble-left-right class="w-6 h-6" />
                    </div>
                </div>

                <!-- KPI 3 -->
                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-400 mb-1 flex items-center gap-1" title="Anzahl der Chats, die von der KI erfolgreich und komplett autonom gelöst wurden.">Durch KI Gelöst <x-heroicon-o-information-circle class="w-4 h-4 opacity-75 cursor-help" /></p>
                        <h3 class="text-3xl font-black text-white">{{ $resolvedCount }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-green-400/20 flex items-center justify-center text-green-500 ring-1 ring-green-400/30">
                        <x-heroicon-o-check-circle class="w-6 h-6" />
                    </div>
                </div>
            </div>

            {{-- 1.5 Telemetry KPIs --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
                <!-- KPI 4: Unique KI Nutzer -->
                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 p-6 flex flex-col justify-between">
                    <p class="text-sm font-medium text-purple-400 mb-1 flex items-center gap-1" title="Anzahl der einzigartigen Nutzer (Kunden + Session-Gäste), die in diesem Zeitraum mit der KI interagiert haben.">KI-Nutzer (Unique) <x-heroicon-o-information-circle class="w-4 h-4 opacity-75 cursor-help" /></p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-white">{{ $uniqueAiCustomers }}</h3>
                        <div class="w-10 h-10 rounded-xl bg-purple-400/20 flex items-center justify-center text-purple-500 ring-1 ring-purple-400/30">
                            <x-heroicon-o-users class="w-5 h-5" />
                        </div>
                    </div>
                </div>

                <!-- KPI 5: Avg Response Time -->
                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 p-6 flex flex-col justify-between">
                    <p class="text-sm font-medium text-amber-400 mb-1 flex items-center gap-1" title="Die durchschnittliche Zeit in Millisekunden, die die KI für einen kompletten Antwortzyklus (inklusive Tools & RAG) benötigt.">Ø KI-Antwortzeit <x-heroicon-o-information-circle class="w-4 h-4 opacity-75 cursor-help" /></p>
                    <div class="flex items-end justify-between">
                        <h3 class="text-3xl font-black text-white">{{ $avgResponseTime }} <span class="text-lg text-gray-500 font-normal">ms</span></h3>
                        <div class="w-10 h-10 rounded-xl bg-amber-400/20 flex items-center justify-center text-amber-500 ring-1 ring-amber-400/30">
                            <x-heroicon-o-bolt class="w-5 h-5" />
                        </div>
                    </div>
                </div>

                <!-- KPI 6: Confidence Score -->
                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 p-6 flex flex-col justify-between">
                    <p class="text-sm font-medium text-emerald-400 mb-1 flex items-center gap-1" title="Gibt an, wie sicher sich die KI bei ihren Ausgaben und Bereitstellungen ist. Berechnet aus den Konfidenzwerten der jeweils präferierten Werkzeuge (z.B. Lösungswerkzeug = 100%, Eskalationswerkzeug = 40%).">Ø KI-Confidence <x-heroicon-o-information-circle class="w-4 h-4 opacity-75 cursor-help" /></p>
                        <h3 class="text-3xl font-black text-white">{{ $avgConfidence }}%</h3>
                        <div class="w-10 h-10 rounded-xl bg-emerald-400/20 flex items-center justify-center text-emerald-500 ring-1 ring-emerald-400/30">
                            <x-heroicon-o-shield-check class="w-5 h-5" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Top Topics & Products --}}
            <div class="grid grid-cols-1 my-12 lg:grid-cols-2 gap-8 lg:gap-10">
                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-700/50 bg-gray-800/50">
                        <h3 class="font-bold text-gray-100 flex items-center gap-2">
                            <x-heroicon-o-hashtag class="w-5 h-5 text-cyan-400" />
                            Top 5 Cloud Themen
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($topTopics->isEmpty())
                            <p class="text-gray-400 text-sm">Noch keine analytischen Daten verfügbar.</p>
                        @else
                            <ul class="space-y-4">
                                @foreach($topTopics as $topic)
                                    <li class="flex items-center justify-between">
                                        <span class="text-gray-300 font-medium text-sm">{{ $topic->top_topic }}</span>
                                        <span class="bg-gray-700 text-gray-300 py-1 px-3 rounded-full text-xs font-bold">{{ $topic->count }}x</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-700/50 bg-gray-800/50">
                        <h3 class="font-bold text-gray-100 flex items-center gap-2">
                            <x-heroicon-o-shopping-bag class="w-5 h-5 text-cyan-400" />
                            Top 5 Erwähnte Produkte
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($topProducts->isEmpty())
                            <p class="text-gray-400 text-sm">Noch keine Produkt-Analysen verfügbar.</p>
                        @else
                            <ul class="space-y-4">
                                @foreach($topProducts as $prod)
                                    <li class="flex items-center justify-between">
                                        <span class="text-gray-300 font-medium text-sm">{{ $prod->mentioned_product }}</span>
                                        <span class="bg-cyan-500/20 text-cyan-400 py-1 px-3 rounded-full text-xs font-bold ring-1 ring-cyan-500/50">{{ $prod->count }}x</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 2.5 Ratings Breakdown --}}
            <div class="bg-gray-800 rounded-2xl shadow-lg border border-gray-700/50 mb-12 p-8 pt-10 relative overflow-hidden group">
                <div class="flex flex-col md:flex-row md:items-start gap-8 w-full">
                    <div class="flex flex-col items-center md:items-start shrink-0">
                        <h3 class="text-white text-xl font-serif font-semibold drop-shadow-sm flex items-center gap-2 mb-2">
                            <x-heroicon-o-star class="w-5 h-5 text-amber-500" />
                            Kundenbewertungen (Chat)
                        </h3>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-5xl font-black text-white">{{ number_format($avgRating, 1, ',', '.') }}</span>
                            <span class="text-lg text-gray-400 mt-2">von 5</span>
                        </div>
                        <span class="text-sm text-gray-500">{{ $totalRatings }} abgegebene Bewertungen</span>
                    </div>

                    <div class="flex-1 w-full border-t md:border-t-0 md:border-l border-gray-700/50 pt-6 md:pt-0 md:pl-8">
                        @foreach([5, 4, 3, 2, 1] as $star)
                            <div class="flex items-center w-full group mb-3 last:mb-0 transition-opacity">
                                <span class="text-sm font-medium text-gray-400 w-16 text-left whitespace-nowrap">{{ $star }} Sterne</span>
                                <div class="flex-1 mx-4 h-5 bg-gray-900 rounded-full overflow-hidden border border-gray-700/50 shadow-inner">
                                    <div class="h-full bg-amber-500 rounded-full transition-all duration-500" style="width: {{ $ratingBreakdown[$star]['percent'] }}%;"></div>
                                </div>
                                <span class="text-sm text-gray-400 w-12 text-right">{{ $ratingBreakdown[$star]['percent'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 3. Data Table --}}
            <div class="bg-gray-800 my-12 rounded-2xl shadow-lg border border-gray-700/50 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700/50 flex flex-col sm:flex-row justify-between items-center bg-gray-800/50 gap-4">
                    <h3 class="font-bold text-gray-100 uppercase tracking-widest text-xs">Chat Protokolle</h3>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <select wire:model.live="statusFilter" class="bg-gray-900 border border-gray-700 text-white rounded-xl text-sm w-full sm:w-auto">
                            <option value="">Alle Status</option>
                            <option value="needs_employee">Eskalation (Mitarbeiter)</option>
                            <option value="open">Offen</option>
                            <option value="resolved">Erledigt</option>
                        </select>
                        <select wire:model.live="ratingFilter" class="bg-gray-900 border border-gray-700 text-white rounded-xl text-sm w-full sm:w-auto">
                            <option value="">Alle Bewertungen</option>
                            <option value="5">5 Sterne</option>
                            <option value="4">4 Sterne</option>
                            <option value="3">3 Sterne</option>
                            <option value="2">2 Sterne</option>
                            <option value="1">1 Stern</option>
                        </select>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche in Thematik..." class="w-full sm:w-64 bg-gray-900 border border-gray-700 text-white placeholder-gray-500 text-sm rounded-xl px-4 py-2 focus:ring-cyan-500 focus:border-cyan-500">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-900/50 border-b border-gray-700/50">
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Thema / Fokusprodukt</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Nachrichten</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Sterne</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Analytische Zusammenfassung</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Aktion</th>
                            </tr>
                        </thead>
                        @forelse($chats as $chat)
                            <tbody x-data="{ expanded: false }" wire:key="chat-{{ $chat->id }}">
                                <tr @click="expanded = !expanded" class="hover:bg-gray-700/20 transition-colors cursor-pointer border-b border-gray-700/50 {{ $chat->status === 'needs_employee' ? 'bg-red-500/5' : '' }}">
                                        <td class="p-4">
                                        @if($chat->status === 'needs_employee')
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-red-500/20 text-red-400 border border-red-500/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                                Mitarbeiter benötigt
                                            </span>
                                        @elseif($chat->status === 'open')
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                                                Offen
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-500/20 text-green-400 border border-green-500/30">
                                                Erledigt
                                            </span>
                                        @endif
                                        <div class="text-[10px] text-gray-500 mt-2 ml-1">{{ $chat->updated_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-sm text-gray-200 font-semibold truncate max-w-[200px]" title="{{ $chat->top_topic }}">{{ $chat->top_topic ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 truncate mt-1">Prod: {{ $chat->mentioned_product ?? '-' }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <x-heroicon-o-chat-bubble-bottom-center-text class="w-5 h-5 text-gray-400" />
                                            <span class="text-sm text-gray-300 font-bold">{{ $chat->messages->count() }}</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        @if($chat->rating)
                                            <div class="flex justify-center items-center text-amber-500 font-bold gap-1 text-sm">
                                                {{ $chat->rating }} <x-heroicon-s-star class="w-4 h-4" />
                                            </div>
                                        @else
                                            <span class="text-gray-600 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        <p class="text-xs text-gray-400 line-clamp-2 max-w-xs">{{ $chat->ai_summary ?? 'Keine KI-Zusammenfassung vorhanden.' }}</p>
                                    </td>
                                    <td class="p-4 text-right">
                                        @if($chat->status === 'needs_employee')
                                            <button wire:click="markAsResolved('{{ $chat->id }}')" class="px-4 py-2 bg-gray-700 hover:bg-green-600 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-xl text-xs text-white transition-colors shadow-sm">
                                                Als Erledigt markieren
                                            </button>
                                        @else
                                            <span class="text-gray-500 text-xs italic">Verwaltet von {{ $agentName }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr x-show="expanded" x-transition.opacity class="bg-gray-900/40">
                                    <td colspan="6" class="p-6">
                                        <div class="space-y-6">
                                            @if($chat->rating)
                                                <div class="flex items-start gap-4 bg-gray-800 p-4 rounded-xl border border-gray-700">
                                                    <div>
                                                        <div class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Kundenbewertung</div>
                                                        <div class="flex items-center gap-1 text-yellow-500">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                @if($i <= $chat->rating)
                                                                    <x-heroicon-s-star class="w-5 h-5" />
                                                                @else
                                                                    <x-heroicon-o-star class="w-5 h-5 text-gray-600" />
                                                                @endif
                                                            @endfor
                                                        </div>
                                                    </div>
                                                    @if($chat->feedback_text)
                                                        <div class="flex-1 ml-4 border-l border-gray-700 pl-4">
                                                            <div class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Kundenfeedback</div>
                                                            <p class="text-sm text-gray-300 italic">"{{ $chat->feedback_text }}"</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden shadow-inner">
                                                <div class="px-4 py-3 bg-gray-900/80 border-b border-gray-700 flex justify-between items-center">
                                                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Chat Verlauf ({{ $chat->messages->count() }} Nachrichten)</span>
                                                </div>
                                                <div class="p-4 space-y-4 max-h-[400px] overflow-y-auto">
                                                    @foreach($chat->messages as $msg)
                                                        <div class="flex {{ $msg->sender === 'customer' ? 'justify-end' : 'justify-start' }} items-end gap-2">
                                                            @if($msg->sender !== 'customer' && $msg->sender !== 'system')
                                                                <img src="{{ $agentImage }}" class="w-7 h-7 rounded-full border border-gray-600/50 shadow-sm shrink-0 mb-1">
                                                            @endif
                                                            <div class="max-w-[75%] px-4 py-3 rounded-2xl shadow-sm {{ $msg->sender === 'customer' ? 'bg-cyan-600/20 text-cyan-50 rounded-br-sm border border-cyan-500/30' : ($msg->sender === 'system' ? 'bg-gray-700/50 text-gray-400 rounded-xl border border-gray-600/30 w-full text-center text-xs' : 'bg-gray-700/80 text-gray-200 rounded-bl-sm border border-gray-600/50') }}">
                                                                @if($msg->sender !== 'system')
                                                                    <div class="text-[10px] opacity-70 mb-1 {{ $msg->sender === 'customer' ? 'text-right text-cyan-200' : 'text-left text-gray-400' }}">
                                                                        {{ $msg->sender === 'customer' ? 'Kunde' : $agentName }} • {{ $msg->created_at->format('H:i:s') }}
                                                                    </div>
                                                                @endif
                                                                <p class="text-sm whitespace-pre-wrap leading-relaxed">{!! \Illuminate\Support\Str::markdown($msg->message) !!}</p>
                                                            </div>
                                                            @if($msg->sender === 'customer')
                                                                <div class="w-7 h-7 rounded-full bg-cyan-700/50 border border-cyan-500/30 flex items-center justify-center shrink-0 mb-1">
                                                                    <x-heroicon-s-user class="w-4 h-4 text-cyan-200" />
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            @empty
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-gray-500 text-sm border-b border-gray-700/50">
                                            Es wurden derzeit noch keine Support-Chats durch die KI verarbeitet.
                                        </td>
                                    </tr>
                                </tbody>
                            @endforelse
                    </table>
                </div>
                @if($chats->hasPages())
                    <div class="p-4 border-t border-gray-700/50 bg-gray-800/30">
                        {{ $chats->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
