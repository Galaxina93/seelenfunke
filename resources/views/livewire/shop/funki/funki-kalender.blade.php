<div class="flex flex-col h-full bg-white rounded-[1.5rem] md:rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden min-h-[600px] md:min-h-[700px] relative"
     x-data="{
        isMobile: window.innerWidth < 768,
        handleDateClick(date) {
            if (this.isMobile) {
                $wire.createEvent(date);
            }
        },
        handleDateDblClick(date) {
            if (!this.isMobile) {
                $wire.createEvent(date);
            }
        }
     }"
     @resize.window="isMobile = window.innerWidth < 768">

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

    {{-- 1. HEADER BEREICH --}}
    <div class="p-3 md:p-6 border-b border-slate-50 bg-white shrink-0 z-20">
        <div class="flex flex-col gap-4">
            {{-- Obere Leiste: Titel & Actions --}}
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl md:text-3xl font-serif font-bold text-slate-900 leading-none">
                        @if($view === 'year') {{ $currentDate->year }}
                        @elseif($view === 'multi-week') 4-Wochen
                        @else {{ $currentDate->locale('de')->monthName }} <span class="text-slate-300 font-sans hidden sm:inline">{{ $currentDate->year }}</span>
                        @endif
                    </h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="px-1.5 py-0.5 rounded bg-primary/10 text-primary text-[9px] font-black uppercase tracking-widest leading-none">KW {{ $currentDate->weekOfYear }}</span>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider hidden sm:block">Dein Planer</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Import Tool --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="h-10 w-10 md:w-auto md:px-4 bg-slate-900 text-white rounded-xl shadow-lg flex items-center justify-center gap-2 text-xs font-bold transition-all active:scale-95">
                            <x-heroicon-m-arrow-up-tray class="w-5 h-5 md:w-4 md:h-4" />
                            <span class="hidden md:inline font-black uppercase tracking-widest text-[10px]">Import</span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 top-full mt-2 w-72 bg-white rounded-2xl shadow-2xl border border-slate-100 p-4 z-50 animate-fade-in-up">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wide mb-2">ICS Kalender Import</h4>
                            <input type="file" wire:model="importFile" class="text-[10px] w-full file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-50 mb-3">
                            <div wire:loading wire:target="importFile" class="text-[10px] text-primary font-bold animate-pulse mb-2">Verarbeite Datei...</div>
                            @if($importFile)
                                <button wire:click="importEvents" class="w-full bg-primary text-white py-2.5 rounded-xl text-xs font-black uppercase tracking-widest shadow-md hover:bg-primary-dark">Import starten</button>
                            @endif
                        </div>
                    </div>

                    {{-- Neu Button --}}
                    <button wire:click="createEvent" class="h-10 w-10 md:w-auto md:px-4 bg-primary text-white rounded-xl shadow-lg flex items-center justify-center gap-2 text-xs font-bold transition-all active:scale-95">
                        <x-heroicon-m-plus class="w-6 h-6 md:w-4 md:h-4" />
                        <span class="hidden md:inline font-black uppercase tracking-widest text-[10px]">Neu</span>
                    </button>
                </div>
            </div>

            {{-- Untere Leiste: Navigation --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex items-center justify-between bg-slate-50 p-1 rounded-xl shadow-inner sm:w-auto">
                    <button wire:click="prev" class="p-2 hover:bg-white rounded-lg transition text-slate-400 hover:text-slate-800">
                        <x-heroicon-m-chevron-left class="w-5 h-5" />
                    </button>
                    <button wire:click="today" class="px-4 py-1.5 bg-white shadow-sm rounded-lg text-[10px] font-black text-slate-700 uppercase tracking-widest">Heute</button>
                    <button wire:click="next" class="p-2 hover:bg-white rounded-lg transition text-slate-400 hover:text-slate-800">
                        <x-heroicon-m-chevron-right class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex p-1 bg-slate-50 rounded-xl shadow-inner overflow-x-auto no-scrollbar touch-pan-x">
                    @foreach(['year' => 'Jahr', 'month' => 'Monat', 'multi-week' => '4-W', 'week' => 'Woche', 'day' => 'Tag', 'list' => 'Liste'] as $v => $label)
                        <button wire:click="setView('{{ $v }}')"
                                class="whitespace-nowrap px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-tight transition {{ $view === $v ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 2. FEEDBACK MESSAGES --}}
    @if(session()->has('calendar_success'))
        <div class="bg-emerald-50 text-emerald-600 px-6 py-2 text-[10px] font-bold text-center border-b border-emerald-100 flex justify-center items-center gap-2 animate-fade-in">
            <x-heroicon-s-check-circle class="w-4 h-4" /> {{ session('calendar_success') }}
        </div>
    @endif

    {{-- 3. KALENDER CONTENT (SCROLLABLE) --}}
    <div class="flex-1 overflow-auto custom-scrollbar bg-slate-50/30">

        {{-- MONATS- & 4-WOCHEN GRID --}}
        @if($view === 'month' || $view === 'multi-week')
            <div class="min-w-[700px] md:min-w-[800px] h-full p-2 md:p-4">
                <div class="grid grid-cols-7 gap-1 md:gap-3 h-full">
                    @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)
                        <div class="text-center py-1 text-[9px] font-black uppercase text-slate-400 tracking-tighter">{{ $day }}</div>
                    @endforeach

                    @foreach($this->calendarGrid as $day)
                        <div @click="handleDateClick('{{ $day['date']->format('Y-m-d') }}')"
                             @dblclick="handleDateDblClick('{{ $day['date']->format('Y-m-d') }}')"
                            @class([
                           'min-h-[80px] md:min-h-[100px] bg-white rounded-xl md:rounded-2xl p-1.5 flex flex-col transition-all border relative cursor-pointer select-none',
                           'border-slate-100 hover:border-blue-200' => $day['is_current_month'],
                           'border-transparent bg-slate-50/50 opacity-40' => !$day['is_current_month'],
                           'ring-2 ring-primary ring-offset-1 z-10' => $day['is_today']
                       ])>
                            <div class="flex justify-between items-start mb-1">
                                <span @class([
                                    'text-[10px] md:text-sm font-bold w-5 h-5 md:w-7 md:h-7 flex items-center justify-center rounded-full',
                                    'bg-slate-900 text-white shadow-md font-black' => $day['is_today'],
                                    'text-slate-700' => !$day['is_today'] && $day['is_current_month']
                                ])>
                                    {{ $day['date']->day }}
                                </span>
                                @if($day['date']->day === 1)
                                    <span class="text-[8px] font-black uppercase text-slate-300">{{ $day['date']->locale('de')->shortMonthName }}</span>
                                @endif
                            </div>

                            <div class="space-y-1 flex-1 overflow-y-auto no-scrollbar">
                                @foreach($day['events'] as $event)
                                    @php $s = $styles[$event->category] ?? $styles['general']; @endphp
                                    <div wire:click.stop="editEvent('{{ $event->id }}')"
                                         class="px-1.5 py-0.5 rounded {{ $s['bg'] }} {{ $s['text'] }} w-full text-[8px] md:text-[9px] font-bold truncate transition-all shadow-sm flex items-center justify-between">
                                        <span class="truncate">{{ $event->title }}</span>
                                        @if($event->reminder_minutes) <x-heroicon-s-bell class="w-2 h-2 opacity-50 shrink-0 ml-0.5" /> @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- JAHRESANSICHT --}}
        @elseif($view === 'year')
            <div class="p-3 md:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4 md:gap-6">
                @foreach($this->yearGrid as $month)
                    <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm hover:border-primary/20 transition-all group">
                        <h4 class="text-xs font-black text-slate-900 mb-3 uppercase tracking-widest border-b border-slate-50 pb-2 group-hover:text-primary transition-colors">{{ $month['name'] }}</h4>
                        <div class="grid grid-cols-7 gap-1 text-center text-[7px] font-black text-slate-300 mb-1">
                            <span>M</span><span>D</span><span>M</span><span>D</span><span>F</span><span>S</span><span>S</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center">
                            @for($i = 0; $i < ($month['days'][0]['date']->dayOfWeekIso - 1); $i++) <div></div> @endfor
                            @foreach($month['days'] as $day)
                                <div class="aspect-square flex items-center justify-center cursor-pointer" wire:click="goToDay('{{ $day['date']->format('Y-m-d') }}')">
                                    <div @class([
                                        'w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold transition-colors',
                                        'bg-primary text-white shadow-sm font-black scale-110' => $day['has_events'],
                                        'text-slate-400 hover:bg-slate-50' => !$day['has_events'],
                                        'ring-1 ring-slate-900 text-slate-900' => $day['date']->isToday() && !$day['has_events']
                                    ])>{{ $day['day'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- TERMINÜBERSICHT (LISTE) --}}
        @elseif($view === 'list')
            <div class="max-w-3xl mx-auto py-4 md:py-8 px-3 md:px-4 space-y-6">
                @foreach($this->events->groupBy(fn($e) => $e->start_date->format('Y-m-d')) as $dateKey => $events)
                    @php $dayDate = \Carbon\Carbon::parse($dateKey); @endphp
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 px-2">
                            <span class="text-xs font-black text-slate-900 uppercase tracking-widest">{{ $dayDate->locale('de')->dayName }}</span>
                            <div class="h-px bg-slate-100 flex-1"></div>
                            <span class="text-[10px] font-bold text-slate-400">{{ $dayDate->format('d.m.Y') }}</span>
                        </div>
                        <div class="bg-white rounded-[1.5rem] p-2 shadow-sm border border-slate-100 space-y-1">
                            @foreach($events as $event)
                                @php $s = $styles[$event->category] ?? $styles['general']; @endphp
                                <div class="flex items-center gap-3 p-3 hover:bg-slate-50 rounded-xl cursor-pointer transition-colors group" wire:click="editEvent('{{ $event->id }}')">
                                    <div class="w-12 h-12 md:w-14 md:h-14 bg-slate-50 rounded-xl border border-slate-100 shrink-0 flex flex-col items-center justify-center group-hover:bg-white transition-colors">
                                        <span class="text-lg font-black text-slate-900 leading-none">{{ $dayDate->day }}</span>
                                        <span class="text-[8px] font-bold text-slate-400 uppercase">{{ $dayDate->locale('de')->shortMonthName }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-bold text-slate-800 truncate">{{ $event->title }}</h4>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded {{ $s['bg'] }} {{ $s['text'] }}">{{ $s['label'] }}</span>
                                            <span class="text-[10px] font-mono font-bold text-slate-400">
                                                @if($event->is_all_day) Ganztägig @else {{ $event->start_date->format('H:i') }} @endif
                                            </span>
                                        </div>
                                    </div>
                                    <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:text-primary transition-colors">
                                        <x-heroicon-m-chevron-right class="w-4 h-4" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if($this->events->isEmpty())
                    <div class="text-center py-20 bg-white rounded-[2rem] border border-dashed border-slate-200">
                        <x-heroicon-o-calendar class="w-12 h-12 text-slate-200 mx-auto mb-3" />
                        <p class="text-sm text-slate-400 font-medium italic">Keine Termine für diesen Zeitraum.</p>
                    </div>
                @endif
            </div>

            {{-- WOCHEN- / TAGESANSICHT --}}
        @elseif($view === 'week' || $view === 'day')
            <div class="max-w-4xl mx-auto py-4 px-3 space-y-6">
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
                    <div class="relative pl-8 md:pl-12 pb-4">
                        {{-- Timeline Dot --}}
                        <div @class(['absolute left-0 top-2 w-4 h-4 rounded-full border-4 border-white z-10 shadow-sm transition-all', 'bg-primary ring-4 ring-primary/20 scale-125' => $dayData['is_today'], 'bg-slate-200' => !$dayData['is_today']])></div>
                        @if(!$loop->last) <div class="absolute left-[7px] top-4 bottom-0 w-0.5 bg-slate-100"></div> @endif

                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 mb-4">
                            <h4 @class(['text-lg font-black uppercase tracking-widest', 'text-primary' => $dayData['is_today'], 'text-slate-400' => !$dayData['is_today']])>
                                {{ $dayData['date']->locale('de')->dayName }} <span class="text-slate-900 font-serif ml-1">{{ $dayData['date']->day }}.</span>
                            </h4>
                        </div>

                        <div class="grid gap-2">
                            @forelse($dayData['events'] as $event)
                                @php $s = $styles[$event->category] ?? $styles['general']; @endphp
                                <div wire:click="editEvent('{{ $event->id }}')" class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all cursor-pointer flex items-center justify-between group/card">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $s['bg'] }} {{ $s['text'] }} shadow-md">
                                            <x-dynamic-component :component="'heroicon-o-' . $s['icon']" class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-bold text-slate-800">{{ $event->title }}</h5>
                                            <p class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest">{{ $s['label'] }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-mono font-black text-slate-900 bg-slate-50 px-3 py-1 rounded-lg border border-slate-200">
                                            @if($event->is_all_day) TAG @else {{ $event->start_date->format('H:i') }} @endif
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="bg-slate-50/50 rounded-xl p-3 border border-dashed border-slate-200 text-[10px] font-bold text-slate-300 uppercase tracking-widest">Keine Termine</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- 4. MODAL BEREICH (VOLLSTÄNDIG & OPTIMIERT) --}}
    @if($showEditModal)
        <div class="fixed inset-0 z-[100] flex items-end md:items-center justify-center p-0 md:p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white w-full md:max-w-lg h-[95vh] md:h-auto md:max-h-[90vh] rounded-t-[2.5rem] md:rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col transform animate-modal-up border-t md:border border-slate-200">

                {{-- Header --}}
                <div class="bg-slate-50 px-6 py-4 md:px-8 md:py-6 border-b border-slate-100 flex justify-between items-center shrink-0">
                    <div>
                        <h3 class="text-xl md:text-2xl font-serif font-bold text-slate-900 tracking-tight">{{ $editingEventId ? 'Termin ändern' : 'Neuer Eintrag' }}</h3>
                        <p class="text-[9px] font-black uppercase text-primary tracking-[0.2em] mt-0.5">Automatischer Planer</p>
                    </div>
                    <button wire:click="closeModal" class="w-10 h-10 flex items-center justify-center bg-white rounded-full shadow-sm text-slate-400 hover:text-red-500 transition-colors">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>

                {{-- Formular --}}
                <div class="flex-1 overflow-y-auto custom-scrollbar p-6 md:p-8 space-y-6 pb-28 md:pb-8">

                    {{-- Titel --}}
                    <div class="space-y-1.5">
                        <label class="label-xs">Was liegt an?</label>
                        <input type="text" wire:model="editTitle" class="input-primary" placeholder="z.B. Meeting mit Kunden">
                        @error('editTitle') <span class="text-red-500 text-[10px] font-black uppercase ml-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        {{-- Kategorie --}}
                        <div class="col-span-2 sm:col-span-1">
                            <label class="label-xs">Kategorie</label>
                            <select wire:model="editCategory" class="input-primary py-3">
                                @foreach($styles as $key => $style)
                                    <option value="{{ $key }}">{{ $style['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Erinnerung --}}
                        <div class="col-span-2 sm:col-span-1">
                            <label class="label-xs">Erinnerung</label>
                            <select wire:model="editReminderMinutes" class="input-primary py-3">
                                <option value="">Deaktiviert</option>
                                <option value="0">Pünktlich</option>
                                <option value="15">15 Min. vorher</option>
                                <option value="60">1 Std. vorher</option>
                                <option value="1440">1 Tag vorher</option>
                            </select>
                        </div>
                    </div>

                    {{-- Zeitstrahl --}}
                    <div class="bg-slate-50 p-5 rounded-[2rem] border border-slate-100 space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-3">
                            <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Zeitpunkt & Dauer</span>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" wire:model.live="editIsAllDay" class="rounded-md border-slate-300 text-primary focus:ring-primary h-4 w-4">
                                <span class="text-[10px] font-black uppercase text-slate-600 group-hover:text-primary transition-colors">Ganztägig</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <div class="flex flex-col gap-1">
                                    <span class="text-[9px] font-black text-slate-400 uppercase ml-1">Beginn</span>
                                    <input type="date" wire:model="editStartDate" class="bg-white border-slate-200 rounded-xl text-xs font-bold focus:ring-primary shadow-sm py-2.5">
                                    @if(!$editIsAllDay)
                                        <input type="time" wire:model="editStartTime" class="bg-white border-slate-200 rounded-xl text-xs font-bold focus:ring-primary shadow-sm py-2.5 mt-1">
                                    @endif
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex flex-col gap-1">
                                    <span class="text-[9px] font-black text-slate-400 uppercase ml-1">Ende</span>
                                    <input type="date" wire:model="editEndDate" class="bg-white border-slate-200 rounded-xl text-xs font-bold focus:ring-primary shadow-sm py-2.5">
                                    @if(!$editIsAllDay)
                                        <input type="time" wire:model="editEndTime" class="bg-white border-slate-200 rounded-xl text-xs font-bold focus:ring-primary shadow-sm py-2.5 mt-1">
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Wiederholung --}}
                    <div class="space-y-2">
                        <label class="label-xs">Wiederholung (Serie)</label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <select wire:model.live="editRecurrence" class="input-primary flex-1">
                                <option value="">Einmaliger Termin</option>
                                <option value="daily">Täglich</option>
                                <option value="weekly">Wöchentlich</option>
                                <option value="monthly">Monatlich</option>
                                <option value="yearly">Jährlich</option>
                            </select>
                            @if($editRecurrence)
                                <div class="flex items-center gap-2 bg-slate-900 text-white rounded-xl px-4 py-2 animate-fade-in shrink-0">
                                    <span class="text-[9px] font-black uppercase">Endet:</span>
                                    <input type="date" wire:model="editRecurrenceEnd" class="bg-transparent border-0 p-0 text-xs font-bold focus:ring-0">
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Beschreibung --}}
                    <div class="space-y-1.5">
                        <label class="label-xs">Notizen & Details</label>
                        <textarea wire:model="editDescription" rows="3" class="input-primary resize-none text-sm placeholder:italic" placeholder="Hinterlasse hier Details zum Termin..."></textarea>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="p-4 md:p-8 bg-slate-50 border-t border-slate-100 flex flex-row justify-between items-center gap-4 shrink-0 shadow-[0_-10px_30px_rgba(0,0,0,0.02)]">
                    @if($editingEventId)
                        <button wire:click="deleteEvent" wire:confirm="Soll dieser Termin (oder die Serie) wirklich gelöscht werden?" class="h-14 px-5 rounded-2xl text-xs font-black uppercase tracking-widest text-red-500 hover:bg-red-50 transition-all border border-red-100 bg-white shadow-sm flex items-center gap-2">
                            <x-heroicon-o-trash class="w-4 h-4" /> <span class="hidden sm:inline">Löschen</span>
                        </button>
                    @else
                        <div></div>
                    @endif
                    <div class="flex gap-3 flex-1 justify-end">
                        <button wire:click="closeModal" class="h-14 px-6 rounded-2xl text-xs font-black uppercase tracking-widest text-slate-500 bg-white border border-slate-200 hover:bg-slate-100 transition-all">
                            Abbrechen
                        </button>
                        <button wire:click="saveEvent" class="h-14 px-8 rounded-2xl text-xs font-black uppercase tracking-widest text-white bg-slate-900 hover:bg-primary shadow-xl shadow-slate-200 transition-all active:scale-95 flex items-center gap-2">
                            <span wire:loading.remove wire:target="saveEvent">Speichern</span>
                            <span wire:loading wire:target="saveEvent">Lädt...</span>
                            <x-heroicon-m-check class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- STYLES --}}
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .label-xs { display: block; font-size: 9px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 6px; margin-left: 4px; }

        .input-primary {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 1.25rem;
            padding: 0.875rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: #0f172a;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.03);
        }
        .input-primary:focus {
            background-color: white;
            border-color: #C5A059;
            ring: 4px solid rgba(197, 160, 89, 0.1);
            outline: none;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 768px) {
            .animate-modal-up { animation: modal-slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
            @keyframes modal-slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
        }

        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #C5A059; border-radius: 10px; }
    </style>
</div>
