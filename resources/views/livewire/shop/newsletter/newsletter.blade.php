<div class="space-y-8 relative">

    {{-- KPI Header --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in-up">

        {{-- FUNKI TILE: Next Send --}}
        <div class="bg-slate-900 rounded-[2rem] p-7 relative overflow-hidden shadow-xl shadow-blue-900/10 border border-slate-800 group hover:border-blue-500/30 transition-all duration-500">

            {{-- Background Effects --}}
            <div class="absolute inset-0 bg-gradient-to-tr from-blue-600/10 to-transparent animate-pulse"></div>
            <div class="absolute -right-6 -top-6 text-slate-800 transform rotate-12 group-hover:rotate-0 group-hover:scale-110 transition-all duration-700">
                <svg class="w-40 h-40 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="0.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </div>

            <div class="relative z-10 h-full flex flex-col justify-between">
                {{-- Header Label --}}
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)]"></span>
                    <p class="text-[10px] font-black text-blue-300 uppercase tracking-[0.2em]">Autopilot Status</p>
                </div>

                @if($this->nextScheduledSend)
                    {{-- ACTIVE STATE --}}
                    <div>
                        <h3 class="text-white font-black text-xl leading-tight mb-5 drop-shadow-md">
                            {{ $this->nextScheduledSend['subject'] }}
                        </h3>

                        {{-- Funki Pill Badge --}}
                        <div class="inline-flex items-center gap-3 bg-white/5 rounded-2xl px-4 py-2.5 border border-white/10 backdrop-blur-sm group-hover:bg-white/10 transition-colors">
                            <div class="flex items-center gap-2 text-green-400">
                                <span class="w-2 h-2 bg-current rounded-full animate-pulse shadow-[0_0_8px_currentColor]"></span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-white">Aktiv</span>
                            </div>
                            <div class="w-px h-3 bg-white/10"></div>
                            <div class="flex items-center gap-2 text-slate-300">
                                <i class="bi bi-calendar-event text-[10px]"></i>
                                <span class="text-xs font-mono tracking-tight">{{ $this->nextScheduledSend['send_date']->format('d.m.Y') }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- IDLE STATE --}}
                    <div class="flex flex-col h-full justify-center">
                        <h3 class="text-white font-black text-xl mb-2">Alles ruhig im Kosmos</h3>
                        <p class="text-xs text-slate-400 leading-relaxed font-medium max-w-[90%]">
                            Funki hat aktuell keine Mails für die nächsten 365 Tage auf dem Radar.
                        </p>
                        <div class="mt-4 flex items-center gap-2 opacity-50">
                            <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                            <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                            <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Active Automations --}}
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col justify-center">
            <div class="flex items-center justify-between mb-2">
                <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Aktive Automatismen</span>
                <span class="bg-orange-100 text-orange-600 text-xs font-bold px-2 py-1 rounded-full">{{ $activeTemplatesCount }} / {{ count($availableEvents) }}</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                <div class="bg-orange-500 h-2 rounded-full transition-all duration-1000" style="width: {{ count($availableEvents) > 0 ? ($activeTemplatesCount / count($availableEvents)) * 100 : 0 }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-3">Diese Mails werden automatisch jährlich versendet.</p>
        </div>

        {{-- Subscribers --}}
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Empfänger</p>
                <h3 class="text-4xl font-bold text-gray-800">{{ $subscriberCount }}</h3>
            </div>
            <div class="h-12 w-12 bg-blue-50 rounded-full flex items-center justify-center text-blue-500">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex justify-center">
        <div class="bg-white p-1 rounded-xl border border-gray-200 shadow-sm inline-flex">
            <button wire:click="$set('activeTab', 'calendar')" class="px-6 py-2 rounded-lg text-sm font-bold transition-all {{ $activeTab === 'calendar' ? 'bg-orange-500 text-white shadow-md' : 'text-gray-500 hover:bg-gray-50' }}">Kalender</button>
            <button wire:click="$set('activeTab', 'archive')" class="px-6 py-2 rounded-lg text-sm font-bold transition-all {{ $activeTab === 'archive' ? 'bg-orange-500 text-white shadow-md' : 'text-gray-500 hover:bg-gray-50' }}">Archiv / Inaktiv</button>
            <button wire:click="$set('activeTab', 'subscribers')" class="px-6 py-2 rounded-lg text-sm font-bold transition-all {{ $activeTab === 'subscribers' ? 'bg-orange-500 text-white shadow-md' : 'text-gray-500 hover:bg-gray-50' }}">Abonnenten</button>
        </div>
    </div>

    {{-- MAIN CONTENT AREA --}}

    {{-- 1. KALENDER VIEW --}}
    @if($activeTab === 'calendar')
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
            {{-- Toolbar --}}
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center bg-gray-50/50">
                <div class="flex items-center gap-2 bg-white rounded-lg p-1 border border-gray-200 shadow-sm">
                    <button wire:click="$set('calendarView', 'month')" class="px-3 py-1.5 rounded-md text-xs font-bold {{ $calendarView === 'month' ? 'bg-gray-800 text-white' : 'text-gray-500 hover:bg-gray-100' }}">Monat</button>
                    <button wire:click="$set('calendarView', 'year')" class="px-3 py-1.5 rounded-md text-xs font-bold {{ $calendarView === 'year' ? 'bg-gray-800 text-white' : 'text-gray-500 hover:bg-gray-100' }}">Jahr</button>
                </div>

                <div class="flex items-center gap-4">
                    <button wire:click="$set('selectedYear', '{{ $selectedYear - 1 }}')" class="p-2 hover:bg-white rounded-full transition-colors"><svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></button>
                    <span class="text-xl font-bold text-gray-800 font-serif">
                        {{ $calendarView === 'month' ? \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->locale('de')->isoFormat('MMMM YYYY') : 'Jahr ' . $selectedYear }}
                     </span>
                    <button wire:click="$set('selectedYear', '{{ $selectedYear + 1 }}')" class="p-2 hover:bg-white rounded-full transition-colors"><svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></button>
                </div>

                @if($calendarView === 'month')
                    <div class="flex gap-2">
                        <button wire:click="$set('selectedMonth', '{{ $selectedMonth == 1 ? 12 : $selectedMonth - 1 }}'); @if($selectedMonth == 1) $set('selectedYear', '{{ $selectedYear - 1 }}') @endif" class="p-1 hover:bg-white rounded text-gray-400"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg></button>
                        <button wire:click="$set('selectedMonth', '{{ $selectedMonth == 12 ? 1 : $selectedMonth + 1 }}'); @if($selectedMonth == 12) $set('selectedYear', '{{ $selectedYear + 1 }}') @endif" class="p-1 hover:bg-white rounded text-gray-400"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></button>
                    </div>
                @else <div class="w-16"></div> @endif
            </div>

            <div class="p-8">
                {{-- JAHRES LISTE --}}
                @if($calendarView === 'year')
                    <div class="space-y-12">
                        @foreach($this->calendarData->groupBy(fn($d) => $d['date']->format('F')) as $month => $events)
                            <div class="relative pl-8 border-l-2 border-orange-100">
                                <span class="absolute -left-2.5 top-0 w-5 h-5 rounded-full bg-white border-4 border-orange-200"></span>
                                <h4 class="text-lg font-bold text-gray-800 mb-4">{{ $month }}</h4>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    @foreach($events as $event)
                                        @if($event['type'] === 'holiday')
                                            {{-- Feiertag --}}
                                            <div class="flex items-center gap-4 p-4 rounded-2xl bg-gray-50 border border-transparent hover:border-gray-200 transition-colors">
                                                <div class="bg-white w-12 h-12 rounded-xl flex flex-col items-center justify-center shadow-sm text-red-500 font-bold leading-none border border-red-100">
                                                    <span class="text-lg">{{ $event['date']->format('d') }}</span>
                                                </div>
                                                <div class="text-gray-500 font-medium">{{ $event['title'] }}</div>
                                            </div>
                                        @else
                                            {{-- Mail Template --}}
                                            <div wire:click="editTemplate('{{ $event['template_id'] }}')" class="group cursor-pointer relative flex flex-col p-5 rounded-2xl bg-orange-50/50 border border-orange-100 hover:shadow-lg hover:bg-white hover:border-orange-200 transition-all duration-300">
                                                <div class="flex justify-between items-start mb-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="bg-orange-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Auto-Mail</span>
                                                        <span class="text-xs text-orange-600 font-mono">{{ $event['days_before'] }} Tage vor Event</span>
                                                    </div>
                                                    <button wire:click.stop="archiveTemplate('{{ $event['template_id'] }}')" class="text-gray-300 hover:text-red-500 p-1" title="Deaktivieren (Archivieren)">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </div>
                                                <h4 class="font-bold text-gray-800 text-lg group-hover:text-orange-600 transition-colors truncate">{{ str_replace('📧 ', '', $event['title']) }}</h4>
                                                <p class="text-sm text-gray-500 mt-1">Geplanter Versand: <span class="font-bold">{{ $event['date']->format('d.m.Y') }}</span></p>

                                                <div class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <span class="bg-orange-100 text-orange-700 text-xs font-bold px-3 py-1 rounded-full">Bearbeiten</span>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- MONATS GRID --}}
                @else
                    <div class="grid grid-cols-7 mb-4">
                        @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)
                            <div class="text-center text-xs font-bold text-gray-400 uppercase">{{ $day }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-3">
                        @foreach($calendarGrid as $day)
                            <div class="min-h-[140px] border rounded-2xl p-3 flex flex-col relative transition-all {{ $day['is_current_month'] ? 'bg-white border-gray-100 hover:border-gray-300' : 'bg-gray-50 border-transparent opacity-50' }} {{ $day['is_today'] ? 'ring-2 ring-orange-400 shadow-md z-10' : '' }}">
                                <span class="text-sm font-bold {{ $day['is_today'] ? 'text-orange-600' : 'text-gray-400' }}">{{ $day['date']->format('d') }}</span>
                                <div class="mt-2 space-y-1 overflow-y-auto custom-scrollbar">
                                    @foreach($day['events'] as $event)
                                        @if($event['type'] === 'holiday')
                                            <div class="text-[10px] bg-red-50 text-red-500 px-2 py-1 rounded-md font-bold truncate border border-red-100">★ {{ $event['title'] }}</div>
                                        @else
                                            <div wire:click="editTemplate('{{ $event['template_id'] }}')" class="cursor-pointer text-[10px] bg-orange-50 text-orange-700 border border-orange-200 px-2 py-1 rounded-md font-bold shadow-sm hover:bg-orange-100 hover:scale-105 transition-transform truncate">
                                                {{ $event['title'] }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- 2. ARCHIV VIEW --}}
    @elseif($activeTab === 'archive')
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 animate-fade-in">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Deaktivierte Vorlagen (Archiv)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($archivedTemplates as $tmpl)
                    <div class="border border-gray-200 rounded-2xl p-6 bg-gray-50 flex flex-col justify-between opacity-75 hover:opacity-100 transition-opacity">
                        <div>
                            <div class="text-xs font-bold text-gray-400 uppercase mb-2">{{ $availableEvents[$tmpl->target_event_key] ?? $tmpl->target_event_key }}</div>
                            <h3 class="font-bold text-gray-800">{{ $tmpl->title }}</h3>
                            <p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ $tmpl->subject }}</p>
                        </div>
                        <button wire:click="restoreTemplate('{{ $tmpl->id }}')" class="mt-4 w-full py-2 bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-600 hover:bg-green-50 hover:text-green-600 hover:border-green-200 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Wieder aktivieren
                        </button>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-12 text-gray-400">Das Archiv ist leer. Alle Automatismen sind aktiv.</div>
                @endforelse
            </div>
        </div>

        {{-- 3. ABONNENTEN --}}
    @elseif($activeTab === 'subscribers')
        {{-- Standard Table View wie vorher... --}}
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 animate-fade-in">
            {{-- ... (Code identisch zu vorheriger Version für Subscribers) ... --}}
            <div class="overflow-x-auto rounded-xl border border-gray-100">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">
                        <th class="px-6 py-4">E-Mail</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($subscribers as $sub)
                        <tr class="hover:bg-orange-50/30 transition-colors">
                            <td class="px-6 py-4 font-bold text-gray-700">{{ $sub->email }}</td>
                            <td class="px-6 py-4">
                                @if($sub->is_verified) <span class="text-green-600 text-xs font-bold bg-green-100 px-2 py-1 rounded">Verifiziert</span> @else <span class="text-yellow-600 text-xs font-bold bg-yellow-100 px-2 py-1 rounded">Offen</span> @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="deleteSubscriber('{{ $sub->id }}')" class="text-gray-400 hover:text-red-500"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400">Keine Abonnenten.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $subscribers->links() }}</div>
        </div>
    @endif

    {{-- EDIT MODAL --}}
    @if($editingTemplateId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-fade-in">
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden animate-zoom-in flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800">Vorlage anpassen</h3>
                    <button wire:click="cancelEdit" class="text-gray-400 hover:text-gray-600"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>

                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar">
                    <div class="bg-blue-50 text-blue-800 p-4 rounded-xl text-sm mb-4">
                        💡 Du bearbeitest das <strong>globale Template</strong>. Diese Mail wird jedes Jahr automatisch zu diesem Anlass versendet.
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Betreffzeile</label>
                        <input type="text" wire:model="edit_subject" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange-200 focus:border-orange-500 font-bold text-gray-800 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Versandzeitpunkt</label>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-600">Sende diese Mail</span>
                            <input type="number" wire:model="edit_offset" class="w-20 bg-white border border-gray-200 rounded-xl px-3 py-2 text-center font-bold outline-none focus:ring-orange-500">
                            <span class="text-sm text-gray-600">Tage <strong>vor</strong> dem Feiertag.</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">E-Mail Inhalt (HTML)</label>
                        <textarea wire:model="edit_content" rows="10" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-4 font-mono text-sm focus:ring-2 focus:ring-orange-200 focus:border-orange-500 outline-none"></textarea>
                        <p class="text-[10px] text-gray-400 mt-1">Platzhalter: {first_name} wird automatisch ersetzt.</p>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button type="button" wire:click="sendTestMail" wire:loading.attr="disabled" class="btn btn-outline-primary btn-sm">
                            <span wire:loading.remove wire:target="sendTestMail">
                                <i class="bi bi-send-fill me-2"></i>Testmail an mich
                            </span>
                            <span wire:loading wire:target="sendTestMail">
                                <span class="spinner-border spinner-border-sm me-2"></span>Sende...
                            </span>
                        </button>

                        @if(session()->has('test_success'))
                            <span class="text-success small ms-3 anim-fade-in">
                                <i class="bi bi-check-circle-fill me-1"></i> {{ session('test_success') }}
                            </span>
                        @endif
                    </div>

                    <div>
                        <button type="button" wire:click="$set('editingTemplateId', null)" class="btn btn-light">Schließen</button>
                        <button type="button" wire:click="saveTemplate" class="btn btn-primary px-4">Speichern</button>
                    </div>
                </div>
                @if(session()->has('test_success'))
                    <div class="alert alert-success mt-2 mx-3">{{ session('test_success') }}</div>
                @endif
            </div>
        </div>
    @endif
</div>
