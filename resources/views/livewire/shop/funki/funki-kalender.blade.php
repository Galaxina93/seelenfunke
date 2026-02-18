<div class="flex flex-col h-full bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden min-h-[700px] relative">

    @php
        $styles = [
            'restmuell'   => ['bg' => 'bg-slate-800', 'text' => 'text-white', 'icon' => 'trash', 'label' => 'Restmüll'],
            'altpapier'   => ['bg' => 'bg-blue-500', 'text' => 'text-white', 'icon' => 'newspaper', 'label' => 'Papier'],
            'biomuell'    => ['bg' => 'bg-amber-800', 'text' => 'text-white', 'icon' => 'trash', 'label' => 'Bio'],
            'gelber_sack' => ['bg' => 'bg-yellow-400', 'text' => 'text-slate-900', 'icon' => 'shopping-bag', 'label' => 'Gelber Sack'],
            'schadstoffe' => ['bg' => 'bg-red-600', 'text' => 'text-white', 'icon' => 'exclamation-triangle', 'label' => 'Schadstoffe'],
            'sperrmuell'  => ['bg' => 'bg-orange-600', 'text' => 'text-white', 'icon' => 'truck', 'label' => 'Sperrmüll'],
            'gruen'       => ['bg' => 'bg-green-700', 'text' => 'text-white', 'icon' => 'scissors', 'label' => 'Grünabfall'],
            'baum'        => ['bg' => 'bg-emerald-800', 'text' => 'text-white', 'icon' => 'sparkles', 'label' => 'Tannenbaum'],
            'call'        => ['bg' => 'bg-fuchsia-600', 'text' => 'text-white', 'icon' => 'phone', 'label' => 'Anrufe'],
            'meeting'     => ['bg' => 'bg-orange-400', 'text' => 'text-white', 'icon' => 'users', 'label' => 'Besprechung'],
            'birthday'    => ['bg' => 'bg-indigo-600', 'text' => 'text-white', 'icon' => 'cake', 'label' => 'Geburtstag'],
            'vacation'    => ['bg' => 'bg-purple-700', 'text' => 'text-white', 'icon' => 'sun', 'label' => 'Urlaub'],
            'travel'      => ['bg' => 'bg-yellow-300', 'text' => 'text-slate-900', 'icon' => 'globe-alt', 'label' => 'Reise'],
            'project'     => ['bg' => 'bg-teal-900', 'text' => 'text-white', 'icon' => 'briefcase', 'label' => 'Projekte'],
            'customer'    => ['bg' => 'bg-yellow-200', 'text' => 'text-slate-900', 'icon' => 'user', 'label' => 'Kunde'],
            'general'     => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'icon' => 'calendar', 'label' => 'Termin'],
        ];
    @endphp

    {{-- HEADER --}}
    <div class="p-4 md:p-6 border-b border-slate-50 bg-white flex flex-col 2xl:flex-row justify-between items-center gap-6 shrink-0 z-20">

        <div class="text-center 2xl:text-left w-full 2xl:w-auto">
            <div class="flex items-baseline justify-center 2xl:justify-start gap-3">
                <h3 class="text-2xl md:text-3xl font-serif font-bold text-slate-900 leading-none">
                    @if($view === 'year')
                        {{ $currentDate->year }}
                    @elseif($view === 'multi-week')
                        4-Wochen-Ansicht
                    @else
                        {{ $currentDate->locale('de')->monthName }} <span class="text-slate-300 font-sans">{{ $currentDate->year }}</span>
                    @endif
                </h3>
                @if($view !== 'year')
                    <span class="px-2 py-1 rounded-lg bg-primary/10 text-primary text-[10px] font-black uppercase tracking-widest">
                        KW {{ $currentDate->weekOfYear }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1 hidden md:block">
                Dein Planer
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3 w-full 2xl:w-auto">
            {{-- Controls --}}
            <div class="flex items-center gap-1 bg-slate-50 p-1.5 rounded-2xl shadow-inner">
                <button wire:click="prev" class="p-2 hover:bg-white hover:shadow-sm rounded-xl transition text-slate-400 hover:text-slate-800">
                    <x-heroicon-m-chevron-left class="w-5 h-5" />
                </button>
                <button wire:click="today" class="px-4 py-2 bg-white shadow-sm rounded-xl text-xs font-bold text-slate-700 hover:text-primary transition uppercase tracking-wide">
                    Heute
                </button>
                <button wire:click="next" class="p-2 hover:bg-white hover:shadow-sm rounded-xl transition text-slate-400 hover:text-slate-800">
                    <x-heroicon-m-chevron-right class="w-5 h-5" />
                </button>
            </div>

            {{-- Views --}}
            <div class="flex p-1 bg-slate-50 rounded-xl shadow-inner overflow-x-auto max-w-full">
                <button wire:click="setView('year')" class="whitespace-nowrap px-3 py-2 rounded-lg text-xs font-bold transition {{ $view === 'year' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400 hover:text-slate-600' }}">Jahr</button>
                <button wire:click="setView('month')" class="whitespace-nowrap px-3 py-2 rounded-lg text-xs font-bold transition {{ $view === 'month' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400 hover:text-slate-600' }}">Monat</button>
                <button wire:click="setView('multi-week')" class="whitespace-nowrap px-3 py-2 rounded-lg text-xs font-bold transition {{ $view === 'multi-week' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400 hover:text-slate-600' }}">4-Wochen</button>
                <button wire:click="setView('week')" class="whitespace-nowrap px-3 py-2 rounded-lg text-xs font-bold transition {{ $view === 'week' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400 hover:text-slate-600' }}">Woche</button>
                <button wire:click="setView('day')" class="whitespace-nowrap px-3 py-2 rounded-lg text-xs font-bold transition {{ $view === 'day' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400 hover:text-slate-600' }}">Tag</button>
                <button wire:click="setView('list')" class="whitespace-nowrap px-3 py-2 rounded-lg text-xs font-bold transition {{ $view === 'list' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400 hover:text-slate-600' }}">Liste</button>
            </div>

            {{-- Actions --}}
            <button wire:click="createEvent" class="h-full px-3 md:px-4 bg-primary text-white rounded-xl hover:bg-primary-dark transition shadow-lg flex items-center gap-2 text-xs font-bold py-2">
                <x-heroicon-m-plus class="w-4 h-4" /> <span class="hidden md:inline">Neu</span>
            </button>

            {{-- Import --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="h-full px-3 bg-slate-900 text-white rounded-xl hover:bg-black transition shadow-lg shadow-slate-200 flex items-center justify-center gap-2 text-xs font-bold py-2" title="Importieren">
                    <x-heroicon-m-arrow-up-tray class="w-4 h-4" /> <span class="hidden md:inline">Import</span>
                </button>
                <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 top-full mt-2 w-72 bg-white rounded-2xl shadow-2xl border border-slate-100 p-4 z-50">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wide mb-2">ICS Import</h4>
                    <input type="file" wire:model="importFile" class="text-xs w-full file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-50 file:text-slate-700 hover:file:bg-slate-100 mb-2 cursor-pointer">
                    <div wire:loading wire:target="importFile" class="text-[10px] text-primary font-bold animate-pulse mb-2">Lade...</div>
                    @if($importFile)
                        <button wire:click="importEvents" class="w-full bg-primary text-white py-2.5 rounded-xl text-xs font-black uppercase tracking-widest shadow-md hover:bg-primary-dark transition">Starten</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- FEEDBACK --}}
    @if(session()->has('calendar_success'))
        <div class="bg-emerald-50 text-emerald-600 px-6 py-3 text-xs font-bold text-center border-b border-emerald-100 flex justify-center items-center gap-2 animate-fade-in">
            <x-heroicon-s-check-circle class="w-4 h-4" />
            {{ session('calendar_success') }}
        </div>
    @endif

    {{-- KALENDER CONTENT --}}
    <div class="flex-1 overflow-auto custom-scrollbar bg-slate-50/30">

        {{-- GRID VIEW (MONAT & MULTI-WOCHE) --}}
        @if($view === 'month' || $view === 'multi-week')
            <div class="min-w-[800px] h-full p-4">
                <div class="grid grid-cols-7 gap-3 h-full">
                    @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)
                        <div class="text-center py-2 text-[10px] font-black uppercase text-slate-400 tracking-widest">{{ $day }}</div>
                    @endforeach

                    @foreach($this->calendarGrid as $day)
                        <div wire:dblclick="createEvent('{{ $day['date']->format('Y-m-d') }}')"
                            @class([
                           'min-h-[100px] bg-white rounded-2xl p-2 flex flex-col transition-all border group relative cursor-pointer select-none',
                           'border-slate-100 hover:border-blue-300 hover:shadow-md' => $day['is_current_month'],
                           'border-transparent bg-slate-50/50 opacity-50' => !$day['is_current_month'],
                           'ring-2 ring-primary ring-offset-2 z-10' => $day['is_today']
                       ])>
                            <div class="flex justify-between items-start mb-2 px-1">
                                <span @class([
                                    'text-sm font-bold w-7 h-7 flex items-center justify-center rounded-full',
                                    'bg-slate-900 text-white shadow-md' => $day['is_today'],
                                    'text-slate-700' => !$day['is_today'] && $day['is_current_month']
                                ])>
                                    {{ $day['date']->day }}
                                </span>
                                @if($day['date']->day === 1 || $day['is_today'])
                                    <span class="text-[9px] font-black uppercase text-slate-300">{{ $day['date']->locale('de')->shortMonthName }}</span>
                                @endif
                            </div>
                            <div class="space-y-1.5 flex-1 overflow-y-auto custom-scrollbar max-h-[100px]">
                                @foreach($day['events'] as $event)
                                    @php $s = $styles[$event->category] ?? $styles['general']; @endphp
                                    <div wire:click.stop="editEvent('{{ $event->id }}')"
                                         class="flex items-center justify-between gap-1 px-2 py-1.5 rounded-lg {{ $s['bg'] }} {{ $s['text'] }} shadow-sm hover:opacity-90 w-full text-[10px] font-bold transition-all"
                                         title="{{ $event->title }}">
                                        <div class="flex items-center gap-1.5 overflow-hidden">
                                            @if(!$event->is_all_day)
                                                <span class="font-mono text-[9px] opacity-80 shrink-0">{{ $event->start_date->format('H:i') }}</span>
                                            @endif
                                            <span class="truncate">{{ $event->title }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            @if($event->reminder_minutes) <x-heroicon-s-bell class="w-2.5 h-2.5 opacity-80" /> @endif
                                            @if(isset($event->is_recurring_instance)) <x-heroicon-m-arrow-path class="w-2.5 h-2.5 opacity-80" /> @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- JAHRESANSICHT --}}
        @elseif($view === 'year')
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6">
                @foreach($this->yearGrid as $month)
                    <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all">
                        <h4 class="text-sm font-bold text-slate-900 mb-3 uppercase tracking-wide border-b border-slate-50 pb-2">{{ $month['name'] }}</h4>
                        <div class="grid grid-cols-7 gap-1 text-center">
                            @foreach(['M','D','M','D','F','S','S'] as $h)
                                <div class="text-[8px] font-black text-slate-300">{{ $h }}</div>
                            @endforeach

                            @for($i = 0; $i < ($month['days'][0]['date']->dayOfWeekIso - 1); $i++)
                                <div></div>
                            @endfor

                            @foreach($month['days'] as $day)
                                <div class="aspect-square flex items-center justify-center relative cursor-pointer"
                                     wire:click="goToDay('{{ $day['date']->format('Y-m-d') }}')">
                                    <div @class([
                                        'w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-medium transition-colors',
                                        'bg-primary text-white font-bold shadow-sm' => $day['has_events'],
                                        'text-slate-500 hover:bg-slate-100 hover:text-slate-900' => !$day['has_events']
                                    ])>
                                        {{ $day['day'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- LIST VIEW (TERMINÜBERSICHT) --}}
        @elseif($view === 'list')
            <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
                @foreach($this->events->groupBy(fn($e) => $e->start_date->format('Y-m-d')) as $dateKey => $events)
                    @php $dayDate = Carbon\Carbon::parse($dateKey); @endphp

                    <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
                        @foreach($events as $event)
                            @php $s = $styles[$event->category] ?? $styles['general']; @endphp

                            <div class="flex items-center gap-4 py-2 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 rounded-xl transition-colors cursor-pointer p-2"
                                 wire:click="editEvent('{{ $event->id }}')">

                                {{-- Datum Kachel --}}
                                <div class="flex flex-col items-center justify-center w-14 h-14 bg-slate-50 rounded-xl border border-slate-100 shrink-0">
                                    <span class="text-[10px] font-black uppercase text-slate-400 leading-none">{{ $dayDate->locale('de')->isoFormat('MMM') }}</span>
                                    <span class="text-xl font-bold text-slate-900 leading-tight">{{ $dayDate->day }}</span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase leading-none">{{ $dayDate->locale('de')->isoFormat('ddd') }}</span>
                                </div>

                                {{-- Zeit & Infos --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if($event->is_all_day)
                                            <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded uppercase tracking-wider">Ganztägig</span>
                                        @else
                                            <div class="text-xs font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                                {{ $event->start_date->format('H:i') }}
                                                @if($event->end_date && $event->end_date->format('H:i') !== $event->start_date->format('H:i'))
                                                    - {{ $event->end_date->format('H:i') }}
                                                @endif
                                            </div>
                                        @endif
                                        <span class="text-[10px] font-black uppercase tracking-widest {{ str_replace('bg-', 'text-', $s['bg']) }} opacity-80">{{ $s['label'] }}</span>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-900 truncate">{{ $event->title }}</h4>
                                </div>

                                {{-- Icon --}}
                                <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $s['bg'] }} {{ $s['text'] }} shadow-md shrink-0">
                                    <x-dynamic-component :component="'heroicon-o-' . $s['icon']" class="w-5 h-5" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @if($this->events->isEmpty())
                    <div class="text-center py-10 text-slate-400 text-sm font-medium">Keine Termine in diesem Zeitraum.</div>
                @endif
            </div>

            {{-- WOCHEN- / TAGESANSICHT --}}
        @elseif($view === 'week' || $view === 'day')
            <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
                @php
                    $loopDays = $view === 'day'
                        ? [['date' => $currentDate, 'is_today' => $currentDate->isToday(), 'events' => $this->events->filter(fn($e) => $e->start_date->isSameDay($currentDate))]]
                        : $this->calendarGrid;

                    if($view === 'week') {
                        $startW = $currentDate->copy()->startOfWeek();
                        $endW = $currentDate->copy()->endOfWeek();
                        $grouped = $this->events->groupBy(fn($e) => $e->start_date->format('Y-m-d'));
                        $loopDays = [];
                        for($d = $startW->copy(); $d <= $endW; $d->addDay()) {
                            $loopDays[] = [
                                'date' => $d->copy(),
                                'is_today' => $d->isToday(),
                                'events' => $grouped->get($d->format('Y-m-d'), collect())
                            ];
                        }
                    }
                @endphp

                @foreach($loopDays as $dayData)
                    <div class="flex flex-col md:flex-row gap-6 group">
                        <div class="w-full md:w-32 md:text-right pt-2 shrink-0 border-b md:border-0 border-slate-100 pb-2 md:pb-0">
                            <div class="text-xs font-black uppercase text-slate-400 tracking-wider mb-1">
                                {{ $dayData['date']->locale('de')->dayName }}
                            </div>
                            <div @class([
                                'text-3xl font-serif font-bold transition-colors',
                                'text-primary' => $dayData['is_today'],
                                'text-slate-900' => !$dayData['is_today']
                            ])>
                                {{ $dayData['date']->day }}. <span class="text-sm text-slate-300 md:hidden">{{ $dayData['date']->locale('de')->monthName }}</span>
                            </div>
                            <div class="text-sm font-bold text-slate-300 hidden md:block">{{ $dayData['date']->locale('de')->monthName }}</div>
                        </div>

                        <div class="flex-1 pb-8 md:border-l-2 md:border-slate-100 md:pl-8 relative space-y-3">
                            <div class="hidden md:block absolute -left-[9px] top-4 w-4 h-4 rounded-full border-4 border-white {{ $dayData['is_today'] ? 'bg-primary shadow-lg shadow-primary/30' : 'bg-slate-200' }}"></div>

                            @forelse($dayData['events'] as $event)
                                @php $s = $styles[$event->category] ?? $styles['general']; @endphp
                                <div wire:click="editEvent('{{ $event->id }}')"
                                     class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all flex flex-col sm:flex-row sm:items-center justify-between cursor-pointer group/card gap-3">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $s['bg'] }} {{ $s['text'] }} shadow-md shrink-0">
                                            <x-dynamic-component :component="'heroicon-o-' . $s['icon']" class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                                                {{ $event->title }}
                                                @if(isset($event->is_recurring_instance))
                                                    <span class="text-[9px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200 flex items-center gap-0.5">
                                                        <x-heroicon-m-arrow-path class="w-2.5 h-2.5"/> Serie
                                                    </span>
                                                @endif
                                            </h4>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">{{ $s['label'] }}</span>
                                                @if($event->reminder_minutes)
                                                    <span class="text-[10px] text-amber-500 font-bold flex items-center gap-0.5"><x-heroicon-s-bell class="w-3 h-3"/> Alarm an</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-left sm:text-right">
                                        @if(!$event->is_all_day)
                                            <div class="text-xs font-mono font-bold text-slate-500 bg-slate-50 px-3 py-1 rounded-lg border border-slate-200 inline-block">
                                                {{ $event->start_date->format('H:i') }}
                                                @if($event->end_date && $event->end_date->format('H:i') !== $event->start_date->format('H:i'))
                                                    - {{ $event->end_date->format('H:i') }}
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-[10px] font-bold text-slate-300 uppercase tracking-wider bg-slate-50 px-2 py-1 rounded">Ganztägig</div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-slate-300 text-xs italic py-4 pl-2 border-l-2 border-slate-50 ml-1">Keine Termine</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- EDIT MODAL --}}
    @if($showEditModal)
        <div class="absolute inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden transform animate-fade-in-up border border-slate-200 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <div class="bg-slate-50 px-8 py-5 border-b border-slate-100 flex justify-between items-center sticky top-0 z-10">
                    <h3 class="text-xl font-serif font-bold text-slate-900">{{ $editingEventId ? 'Termin bearbeiten' : 'Neuer Termin' }}</h3>
                    <button wire:click="closeModal" class="text-slate-400 hover:text-slate-600"><x-heroicon-m-x-mark class="w-6 h-6" /></button>
                </div>

                <div class="p-8 space-y-6">
                    <div>
                        <label class="label-xs">Titel</label>
                        <input type="text" wire:model="editTitle" class="input-primary" placeholder="Titel des Termins...">
                        @error('editTitle') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="label-xs">Kategorie</label>
                        <div class="grid grid-cols-2 gap-2">
                            <select wire:model="editCategory" class="input-primary col-span-2">
                                @foreach($styles as $key => $style)
                                    <option value="{{ $key }}">{{ $style['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Zeit & Datum --}}
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="label-xs mb-0">Zeitraum</label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="editIsAllDay" class="rounded border-slate-300 text-primary focus:ring-primary">
                                <span class="text-xs font-bold text-slate-600">Ganztägig</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-[9px] uppercase font-bold text-slate-400 block mb-1">Von</span>
                                <input type="date" wire:model="editStartDate" class="input-primary mb-2">
                                @if(!$editIsAllDay) <input type="time" wire:model="editStartTime" class="input-primary"> @endif
                            </div>
                            <div>
                                <span class="text-[9px] uppercase font-bold text-slate-400 block mb-1">Bis</span>
                                <input type="date" wire:model="editEndDate" class="input-primary mb-2">
                                @if(!$editIsAllDay) <input type="time" wire:model="editEndTime" class="input-primary"> @endif
                            </div>
                        </div>
                    </div>

                    {{-- Wiederholung & Erinnerung --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label-xs">Wiederholung</label>
                            <select wire:model.live="editRecurrence" class="input-primary">
                                <option value="">Keine Wiederholung</option>
                                <option value="daily">Täglich</option>
                                <option value="weekly">Wöchentlich</option>
                                <option value="monthly">Monatlich</option>
                                <option value="yearly">Jährlich</option>
                            </select>
                            @if($editRecurrence)
                                <div class="mt-2 animate-fade-in">
                                    <span class="text-[9px] text-slate-400 block mb-1">Endet am (Optional)</span>
                                    <input type="date" wire:model="editRecurrenceEnd" class="input-primary">
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="label-xs">Erinnerung</label>
                            <select wire:model="editReminderMinutes" class="input-primary">
                                <option value="">Keine Erinnerung</option>
                                <option value="0">Zum Zeitpunkt</option>
                                <option value="15">15 Minuten vorher</option>
                                <option value="30">30 Minuten vorher</option>
                                <option value="45">45 Minuten vorher</option>
                                <option value="60">1 Stunde vorher</option>
                                <option value="1440">1 Tag vorher</option>
                                <option value="2880">2 Tage vorher</option>
                                <option value="10080">1 Woche vorher</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label-xs">Notiz</label>
                        <textarea wire:model="editDescription" rows="3" class="input-primary resize-none"></textarea>
                    </div>
                </div>

                <div class="bg-slate-50 px-8 py-5 border-t border-slate-100 flex justify-between gap-4 sticky bottom-0 z-10">
                    @if($editingEventId)
                        <button wire:click="deleteEvent" wire:confirm="Wirklich löschen?" class="text-red-500 hover:bg-red-50 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition">Löschen</button>
                    @else
                        <div></div>
                    @endif
                    <div class="flex gap-3">
                        <button wire:click="closeModal" class="text-slate-400 hover:text-slate-600 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition">Abbrechen</button>
                        <button wire:click="saveEvent" class="bg-slate-900 text-white px-8 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-primary shadow-lg transition">Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
        .label-xs { display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 6px; margin-left: 4px; }
        .input-primary { width: 100%; background-color: #f8fafc; border: none; border-radius: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 700; color: #0f172a; transition: all; }
        .input-primary:focus { background-color: white; ring: 2px solid #C5A059; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    </style>
</div>
