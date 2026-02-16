<div class="space-y-8 relative p-8">

    {{-- KPI Header --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-fade-in-up">

        {{-- ÜBERARBEITET: FUNKI AUTOPILOT TILE (nimmt 2 Spalten ein für mehr Platz) --}}
        <div class="lg:col-span-2 bg-slate-900 rounded-[2.5rem] p-8 relative overflow-hidden shadow-2xl shadow-blue-900/20 border border-slate-800 group hover:border-blue-500/30 transition-all duration-500 min-h-[320px]">

            {{-- Background Effects --}}
            <div class="absolute inset-0 bg-gradient-to-tr from-blue-600/20 via-transparent to-purple-600/10 opacity-50"></div>
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-500/10 blur-[80px] rounded-full"></div>

            <div class="relative z-10 h-full flex flex-col sm:flex-row gap-8 items-center">

                {{-- Funki Avatar Area --}}
                <div class="shrink-0 flex flex-col items-center gap-4">
                    <div class="relative">
                        <div class="absolute inset-0 bg-blue-500 rounded-full blur-2xl opacity-30 animate-pulse"></div>
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                             class="w-32 h-32 rounded-3xl object-cover border-2 border-white/10 shadow-2xl relative z-10 group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute -bottom-2 -right-2 bg-green-500 w-6 h-6 rounded-full border-4 border-slate-900 z-20 shadow-lg"></div>
                    </div>
                    <div class="text-center">
                        <p class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em] mb-1">Status</p>
                        <span class="bg-blue-500/20 text-blue-300 text-[10px] font-black px-3 py-1 rounded-full border border-blue-500/30 backdrop-blur-md">AUTOPILOT ON</span>
                    </div>
                </div>

                {{-- Funki Speech Bubble --}}
                <div class="flex-1 w-full">
                    <div class="bg-white/5 border border-white/10 backdrop-blur-md rounded-[2rem] p-6 relative group-hover:bg-white/10 transition-all duration-500">
                        {{-- Pfeil der Sprechblase --}}
                        <div class="hidden sm:block absolute left-[-10px] top-10 w-0 h-0 border-y-[10px] border-y-transparent border-r-[10px] border-r-white/10"></div>

                        <div class="flex items-center gap-2 mb-4">
                            <span class="w-2 h-2 rounded-full bg-blue-500 shadow-[0_0_10px_#3b82f6]"></span>
                            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em]">Funkis Radar</p>
                        </div>

                        @if($this->nextScheduledSend)
                            <div class="space-y-4">
                                <div>
                                    <p class="text-[10px] text-blue-400 font-bold uppercase mb-1">Nächste Mission:</p>
                                    <h3 class="text-white font-black text-lg leading-tight">
                                        {{ $this->nextScheduledSend['subject'] }}
                                    </h3>
                                </div>

                                {{-- Kleine Liste der nächsten 3 Termine --}}
                                <div class="space-y-2 pt-4 border-t border-white/5">
                                    @php
                                        $upcoming = $this->calendarData->where('is_action', true)->where('status', 'scheduled')->take(3);
                                    @endphp
                                    @foreach($upcoming as $item)
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <i class="bi bi-send-fill text-[10px] text-blue-500"></i>
                                                <span class="text-xs text-slate-300 truncate">{{ str_replace('📧 ', '', $item['title']) }}</span>
                                            </div>
                                            <span class="text-[10px] font-mono text-slate-500 shrink-0">{{ $item['date']->format('d.m.Y') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="py-4 text-center sm:text-left">
                                <h3 class="text-white font-black text-lg mb-1">Orbit ist sauber</h3>
                                <p class="text-xs text-slate-400 leading-relaxed">
                                    Aktuell keine automatisierten Kampagnen für das restliche Jahr geplant.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Automations (1 Column) --}}
        <div class="bg-white rounded-[2.5rem] p-8 border border-gray-100 shadow-sm flex flex-col justify-center group hover:shadow-xl hover:-translate-y-1 transition-all duration-500">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-orange-50 rounded-2xl text-orange-500">
                    <i class="bi bi-cpu-fill fs-4"></i>
                </div>
                <span class="bg-orange-100 text-orange-600 text-xs font-black px-3 py-1 rounded-full">{{ $activeTemplatesCount }} / {{ count($availableEvents) }}</span>
            </div>
            <h4 class="text-gray-900 font-black text-sm uppercase tracking-wider">Automatisierung</h4>
            <div class="w-full bg-gray-100 rounded-full h-2 mt-4 overflow-hidden">
                <div class="bg-orange-500 h-2 rounded-full transition-all duration-1000 shadow-[0_0_10px_#f97316]" style="width: {{ count($availableEvents) > 0 ? ($activeTemplatesCount / count($availableEvents)) * 100 : 0 }}%"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-4 font-bold uppercase tracking-tighter italic">Jährlicher Versand-Zyklus aktiv</p>
        </div>

        {{-- Subscribers (1 Column) --}}
        <div class="bg-white rounded-[2.5rem] p-8 border border-gray-100 shadow-sm flex flex-col justify-between group hover:shadow-xl hover:-translate-y-1 transition-all duration-500">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-blue-50 rounded-2xl text-blue-500">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div class="flex -space-x-2">
                    <div class="w-6 h-6 rounded-full bg-slate-200 border-2 border-white"></div>
                    <div class="w-6 h-6 rounded-full bg-slate-300 border-2 border-white"></div>
                </div>
            </div>
            <div>
                <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest mb-1">Community</p>
                <h3 class="text-4xl font-black text-gray-900 tracking-tighter">{{ $subscriberCount }}</h3>
                <p class="text-[10px] text-green-500 font-bold mt-1">✓ Aktive Abonnenten</p>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex justify-center">
        <div class="bg-gray-100/50 p-1.5 rounded-2xl border border-gray-200 shadow-inner inline-flex gap-1">
            <button wire:click="$set('activeTab', 'calendar')" @class(['px-8 py-3 rounded-xl text-sm font-bold transition-all', 'bg-white text-orange-600 shadow-md border border-gray-100' => $activeTab === 'calendar', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'calendar'])>Kalender</button>
            <button wire:click="$set('activeTab', 'archive')" @class(['px-8 py-3 rounded-xl text-sm font-bold transition-all', 'bg-white text-orange-600 shadow-md border border-gray-100' => $activeTab === 'archive', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'archive'])>Archiv</button>
            <button wire:click="$set('activeTab', 'subscribers')" @class(['px-8 py-3 rounded-xl text-sm font-bold transition-all', 'bg-white text-orange-600 shadow-md border border-gray-100' => $activeTab === 'subscribers', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'subscribers'])>Empfänger</button>
        </div>
    </div>

    {{-- MAIN CONTENT AREA --}}

    {{-- 1. KALENDER VIEW --}}
    @if($activeTab === 'calendar')
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
            {{-- Toolbar --}}
            <div class="p-8 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center bg-gray-50/30">
                <div class="flex items-center gap-2 bg-white rounded-xl p-1 border border-gray-200 shadow-sm">
                    <button wire:click="$set('calendarView', 'month')" class="px-4 py-2 rounded-lg text-xs font-bold transition-all {{ $calendarView === 'month' ? 'bg-slate-900 text-white shadow-md' : 'text-gray-400 hover:bg-gray-50' }}">Monat</button>
                    <button wire:click="$set('calendarView', 'year')" class="px-4 py-2 rounded-lg text-xs font-bold transition-all {{ $calendarView === 'year' ? 'bg-slate-900 text-white shadow-md' : 'text-gray-400 hover:bg-gray-50' }}">Jahr</button>
                </div>

                <div class="flex items-center gap-6">
                    <button wire:click="$set('selectedYear', '{{ $selectedYear - 1 }}')" class="w-10 h-10 flex items-center justify-center hover:bg-white rounded-xl transition-all shadow-sm group border border-transparent hover:border-gray-200">
                        <i class="bi bi-chevron-left text-gray-400 group-hover:text-orange-500"></i>
                    </button>
                    <span class="text-2xl font-serif font-bold text-gray-900">
                        {{ $calendarView === 'month' ? \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->locale('de')->isoFormat('MMMM YYYY') : $selectedYear }}
                     </span>
                    <button wire:click="$set('selectedYear', '{{ $selectedYear + 1 }}')" class="w-10 h-10 flex items-center justify-center hover:bg-white rounded-xl transition-all shadow-sm group border border-transparent hover:border-gray-200">
                        <i class="bi bi-chevron-right text-gray-400 group-hover:text-orange-500"></i>
                    </button>
                </div>

                @if($calendarView === 'month')
                    <div class="flex bg-white rounded-xl p-1 border border-gray-200">
                        <button wire:click="$set('selectedMonth', '{{ $selectedMonth == 1 ? 12 : $selectedMonth - 1 }}')" class="p-2 hover:bg-gray-50 rounded-lg text-gray-400 transition-colors"><i class="bi bi-arrow-left-short fs-5"></i></button>
                        <button wire:click="$set('selectedMonth', '{{ $selectedMonth == 12 ? 1 : $selectedMonth + 1 }}')" class="p-2 hover:bg-gray-50 rounded-lg text-gray-400 transition-colors"><i class="bi bi-arrow-right-short fs-5"></i></button>
                    </div>
                @else <div class="w-24"></div> @endif
            </div>

            <div class="p-8">
                @if($calendarView === 'year')
                    {{-- JAHRES LISTE --}}
                    <div class="space-y-12">
                        @foreach($this->calendarData->groupBy(fn($d) => $d['date']->format('F')) as $month => $events)
                            <div class="relative pl-10 border-l-2 border-orange-100">
                                <span class="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-white border-4 border-orange-500 shadow-sm"></span>
                                <h4 class="text-xl font-black text-gray-900 mb-6 uppercase tracking-widest text-sm">{{ $month }}</h4>

                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                    @foreach($events as $event)
                                        @if($event['type'] === 'holiday')
                                            <div class="flex items-center gap-4 p-5 rounded-[1.5rem] bg-gray-50/50 border border-transparent hover:border-gray-200 transition-all group">
                                                <div class="bg-white w-14 h-14 rounded-2xl flex flex-col items-center justify-center shadow-sm text-red-500 font-black leading-none border border-red-50 group-hover:scale-110 transition-transform">
                                                    <span class="text-lg">{{ $event['date']->format('d') }}</span>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] font-black text-red-400 uppercase tracking-widest">Event</div>
                                                    <div class="text-gray-700 font-bold">{{ $event['title'] }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <div wire:click="editTemplate('{{ $event['template_id'] }}')"
                                                 class="group cursor-pointer relative flex flex-col p-6 rounded-[2rem] bg-orange-50/30 border border-orange-100 hover:shadow-2xl hover:bg-white hover:border-orange-500/20 transition-all duration-500">
                                                <div class="flex justify-between items-start mb-4">
                                                    <div class="flex flex-col gap-1">
                                                        <span class="w-fit bg-orange-500 text-white text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-wider">Newsletter</span>
                                                        <span class="text-[10px] text-orange-600 font-black uppercase">{{ $event['days_before'] }} Tage Vorlauf</span>
                                                    </div>
                                                    <button wire:click.stop="archiveTemplate('{{ $event['template_id'] }}')" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-300 hover:bg-red-50 hover:text-red-500 transition-all">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </div>
                                                <h4 class="font-serif font-black text-gray-900 text-lg leading-tight group-hover:text-orange-600 transition-colors line-clamp-2">
                                                    {{ str_replace('📧 ', '', $event['title']) }}
                                                </h4>
                                                <div class="mt-6 pt-4 border-t border-orange-100 flex items-center justify-between">
                                                    <div class="text-xs text-gray-400">Versand am <span class="font-black text-gray-700">{{ $event['date']->format('d.m.Y') }}</span></div>
                                                    <div class="w-8 h-8 rounded-full bg-orange-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all translate-x-2 group-hover:translate-x-0">
                                                        <i class="bi bi-pencil-fill text-xs"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- MONATS GRID --}}
                    <div class="grid grid-cols-7 mb-6 bg-slate-900 rounded-2xl py-4 shadow-lg">
                        @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)
                            <div class="text-center text-[10px] font-black text-blue-300 uppercase tracking-[0.2em]">{{ $day }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-4">
                        @foreach($calendarGrid as $day)
                            <div @class([
                                'min-h-[160px] border-[1.5px] rounded-[2rem] p-4 flex flex-col relative transition-all duration-300',
                                'bg-white border-gray-100 hover:border-orange-200 hover:shadow-xl hover:-translate-y-1' => $day['is_current_month'],
                                'bg-gray-50/50 border-transparent opacity-40' => !$day['is_current_month'],
                                'ring-4 ring-orange-500/20 border-orange-500 shadow-2xl z-10' => $day['is_today']
                            ])>
                                <span @class(['text-sm font-black', 'text-orange-600' => $day['is_today'], 'text-gray-300' => !$day['is_current_month'], 'text-gray-900' => $day['is_current_month'] && !$day['is_today']])>
                                    {{ $day['date']->format('d') }}
                                </span>

                                <div class="mt-3 space-y-2 overflow-y-auto custom-scrollbar">
                                    @foreach($day['events'] as $event)
                                        @if($event['type'] === 'holiday')
                                            <div class="text-[9px] bg-red-50 text-red-600 px-2.5 py-1.5 rounded-xl font-black uppercase tracking-tighter border border-red-100">
                                                ★ {{ $event['title'] }}
                                            </div>
                                        @else
                                            <div wire:click="editTemplate('{{ $event['template_id'] }}')"
                                                 class="cursor-pointer text-[9px] bg-slate-900 text-white px-2.5 py-1.5 rounded-xl font-bold shadow-md hover:bg-orange-600 transition-all truncate border border-slate-800">
                                                {{ $event['title'] }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                @if($day['is_today'])
                                    <div class="absolute bottom-2 right-4 text-[8px] font-black text-orange-500 uppercase tracking-widest">Heute</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- 2. ARCHIV VIEW --}}
    @elseif($activeTab === 'archive')
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-10 animate-fade-in">
            <div class="mb-10">
                <h2 class="text-2xl font-serif font-bold text-gray-900 italic">Ruhende Vorlagen</h2>
                <p class="text-sm text-gray-500">Diese Mails befinden sich im Tiefschlaf und werden nicht automatisch versendet.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($archivedTemplates as $tmpl)
                    <div class="group border border-gray-200 rounded-[2rem] p-8 bg-gray-50/50 flex flex-col justify-between opacity-80 hover:opacity-100 hover:bg-white hover:border-green-200 transition-all duration-500 hover:shadow-xl">
                        <div>
                            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2">
                                {{ $availableEvents[$tmpl->target_event_key] ?? $tmpl->target_event_key }}
                            </div>
                            <h3 class="font-bold text-gray-900 text-xl">{{ $tmpl->title }}</h3>
                            <p class="text-sm text-gray-500 mt-4 line-clamp-2 italic">"{{ $tmpl->subject }}"</p>
                        </div>
                        <button wire:click="restoreTemplate('{{ $tmpl->id }}')" class="mt-8 w-full py-4 bg-white border border-gray-200 rounded-2xl text-xs font-black uppercase tracking-widest text-gray-600 hover:bg-green-600 hover:text-white hover:border-green-600 transition-all duration-300 shadow-sm hover:shadow-lg flex items-center justify-center gap-3">
                            <i class="bi bi-lightning-charge-fill"></i> Reaktivieren
                        </button>
                    </div>
                @empty
                    <div class="col-span-full py-20 text-center">
                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi bi-box2 text-gray-300 fs-2"></i>
                        </div>
                        <p class="text-gray-400 font-bold uppercase tracking-widest text-xs">Keine inaktiven Vorlagen</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- 3. ABONNENTEN --}}
    @elseif($activeTab === 'subscribers')
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-8 animate-fade-in">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4 px-2">
                <div>
                    <h2 class="text-2xl font-serif font-bold text-gray-900">Seelenfunke-Verteiler</h2>
                    <p class="text-sm text-gray-500">Ihre treuesten Empfänger im Überblick.</p>
                </div>
                <div class="relative w-full sm:w-72">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Empfänger suchen..." class="w-full pl-10 pr-4 py-3 bg-gray-50 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm">
                    <i class="bi bi-search absolute left-4 top-3.5 text-gray-400"></i>
                </div>
            </div>

            <div class="overflow-x-auto rounded-[2rem] border border-gray-100 shadow-inner">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] border-b border-gray-100 bg-gray-50/50">
                        <th class="px-8 py-5">Status</th>
                        <th class="px-8 py-5">E-Mail Adresse</th>
                        <th class="px-8 py-5">Beitritt</th>
                        <th class="px-8 py-5 text-right">Aktion</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    @forelse($subscribers as $sub)
                        <tr class="hover:bg-blue-50/20 transition-colors group">
                            <td class="px-8 py-5">
                                @if($sub->is_verified)
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase text-green-600 bg-green-100 px-3 py-1 rounded-full border border-green-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Verifiziert
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase text-amber-600 bg-amber-100 px-3 py-1 rounded-full border border-amber-200">
                                        Wartend
                                    </span>
                                @endif
                            </td>
                            <td class="px-8 py-5 font-bold text-gray-700">{{ $sub->email }}</td>
                            <td class="px-8 py-5 text-xs text-gray-400">{{ $sub->created_at->format('d. M Y') }}</td>
                            <td class="px-8 py-5 text-right">
                                <button wire:click="deleteSubscriber('{{ $sub->id }}')" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-300 hover:bg-red-50 hover:text-red-500 transition-all opacity-0 group-hover:opacity-100">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-8 py-20 text-center text-gray-400 italic">Keine Empfänger gefunden.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-8">{{ $subscribers->links() }}</div>
        </div>
    @endif

    {{-- EDIT MODAL (Vollständig modernisiert) --}}
    @if($editingTemplateId)
        <div class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/90 backdrop-blur-md p-4 animate-fade-in">
            <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-4xl overflow-hidden animate-zoom-in flex flex-col max-h-[90vh] border border-white/20">
                <div class="p-8 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-orange-500 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-orange-500/30">
                            <i class="bi bi-pencil-square fs-4"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-gray-900 uppercase tracking-tighter">Vorlage bearbeiten</h3>
                            <p class="text-xs text-gray-500 font-bold uppercase tracking-widest">Globales Automation Template</p>
                        </div>
                    </div>
                    <button wire:click="cancelEdit" class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-200 transition-all text-gray-400"><i class="bi bi-x-lg"></i></button>
                </div>

                <div class="p-8 space-y-8 overflow-y-auto custom-scrollbar flex-1">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {{-- Left Column: Settings --}}
                        <div class="space-y-6">
                            <div class="group">
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">E-Mail Betreff</label>
                                <input type="text" wire:model="edit_subject" class="w-full bg-gray-50 border-gray-200 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 font-bold text-gray-800 transition-all outline-none">
                            </div>

                            <div class="bg-orange-50/50 rounded-[2rem] p-6 border border-orange-100">
                                <label class="block text-[10px] font-black text-orange-400 uppercase tracking-[0.2em] mb-4">Timing (Offset)</label>
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        <input type="number" wire:model="edit_offset" class="w-24 bg-white border-orange-200 rounded-xl px-4 py-3 text-center font-black text-orange-600 focus:ring-orange-500 outline-none">
                                        <span class="absolute -top-2 left-3 bg-white px-2 text-[8px] font-black text-orange-400 rounded-full border border-orange-100">Tage</span>
                                    </div>
                                    <p class="text-xs text-orange-800/60 font-medium leading-relaxed">
                                        Diese Mail wird automatisch <span class="font-black text-orange-600">{{ $edit_offset ?: 0 }} Tage</span> vor dem eigentlichen Event-Datum verschickt.
                                    </p>
                                </div>
                            </div>

                            <div class="bg-blue-50/50 rounded-[2rem] p-6 border border-blue-100">
                                <div class="flex items-center gap-4 mb-4">
                                    <i class="bi bi-magic text-blue-500"></i>
                                    <span class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em]">Quality Check</span>
                                </div>
                                <button type="button" wire:click="sendTestMail" wire:loading.attr="disabled" class="w-full py-4 rounded-2xl bg-white border border-blue-200 text-blue-600 font-black text-xs uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all duration-300 shadow-sm flex items-center justify-center gap-3">
                                    <span wire:loading.remove wire:target="sendTestMail"><i class="bi bi-send-check-fill"></i> Testmail an mich</span>
                                    <span wire:loading wire:target="sendTestMail" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i> Sende...</span>
                                </button>
                                @if(session()->has('test_success'))
                                    <p class="text-[10px] text-green-600 font-bold mt-3 text-center animate-bounce">✨ {{ session('test_success') }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Right Column: Content --}}
                        <div class="flex flex-col">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">Inhalt (HTML-Editor)</label>
                            <textarea wire:model="edit_content" class="flex-1 w-full bg-slate-900 border-none rounded-[2rem] p-6 font-mono text-sm text-blue-300 focus:ring-4 focus:ring-blue-500/20 outline-none min-h-[300px] shadow-inner"></textarea>
                            <div class="mt-3 flex justify-between px-2">
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Snippet: {first_name}</span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Autosave: On</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-8 bg-gray-50/50 border-t border-gray-100 flex justify-end gap-4">
                    <button type="button" wire:click="cancelEdit" class="px-8 py-4 rounded-2xl text-xs font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-all">Abbrechen</button>
                    <button type="button" wire:click="saveTemplate" class="px-10 py-4 rounded-2xl bg-slate-900 text-white font-black text-xs uppercase tracking-widest hover:bg-black transition-all shadow-xl shadow-slate-900/20">Änderungen Speichern</button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        @keyframes zoom-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-zoom-in { animation: zoom-in 0.3s ease-out; }
    </style>
</div>
