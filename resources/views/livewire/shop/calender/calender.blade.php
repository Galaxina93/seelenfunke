<div class="flex flex-col h-full bg-gray-900/80 backdrop-blur-xl rounded-[1.5rem] md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden min-h-[600px] md:min-h-[700px] relative"
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
            'restmuell'   => ['bg' => 'bg-gray-800', 'text' => 'text-gray-100', 'icon' => 'trash', 'label' => 'Restmüll'],
            'altpapier'   => ['bg' => 'bg-blue-500/20', 'text' => 'text-blue-400', 'icon' => 'newspaper', 'label' => 'Papier'],
            'biomuell'    => ['bg' => 'bg-amber-900/40', 'text' => 'text-amber-500', 'icon' => 'trash', 'label' => 'Bio'],
            'gelber_sack' => ['bg' => 'bg-yellow-500/20', 'text' => 'text-yellow-400', 'icon' => 'shopping-bag', 'label' => 'Gelber Sack'],
            'schadstoffe' => ['bg' => 'bg-red-500/20', 'text' => 'text-red-400', 'icon' => 'exclamation-triangle', 'label' => 'Schadstoffe'],
            'sperrmuell'  => ['bg' => 'bg-orange-500/20', 'text' => 'text-orange-400', 'icon' => 'truck', 'label' => 'Sperrmüll'],
            'gruen'       => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'icon' => 'scissors', 'label' => 'Grünabfall'],
            'baum'        => ['bg' => 'bg-teal-500/20', 'text' => 'text-teal-400', 'icon' => 'sparkles', 'label' => 'Tannenbaum'],
            'call'        => ['bg' => 'bg-fuchsia-500/20', 'text' => 'text-fuchsia-400', 'icon' => 'phone', 'label' => 'Anrufe'],
            'meeting'     => ['bg' => 'bg-indigo-500/20', 'text' => 'text-indigo-400', 'icon' => 'users', 'label' => 'Besprechung'],
            'birthday'    => ['bg' => 'bg-pink-500/20', 'text' => 'text-pink-400', 'icon' => 'cake', 'label' => 'Geburtstag'],
            'vacation'    => ['bg' => 'bg-cyan-500/20', 'text' => 'text-cyan-400', 'icon' => 'sun', 'label' => 'Urlaub'],
            'travel'      => ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-400', 'icon' => 'globe-alt', 'label' => 'Reise'],
            'project'     => ['bg' => 'bg-primary/20', 'text' => 'text-primary', 'icon' => 'briefcase', 'label' => 'Projekte'],
            'customer'    => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-500', 'icon' => 'user', 'label' => 'Kunde'],
            'general'     => ['bg' => 'bg-gray-800', 'text' => 'text-gray-400', 'icon' => 'calendar', 'label' => 'Termin'],
        ];
    @endphp

    {{-- 1. HEADER BEREICH --}}
    <div class="p-4 md:p-8 border-b border-gray-800 bg-gray-950/50 shrink-0 z-20 shadow-inner">
        <div class="flex flex-col gap-6">
            {{-- Obere Leiste: Titel & Actions --}}
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl md:text-4xl font-serif font-bold text-white leading-none tracking-tight">
                        @if($view === 'year') {{ $currentDate->year }}
                        @elseif($view === 'multi-week') 4-Wochen
                        @else {{ $currentDate->locale('de')->monthName }} <span class="text-gray-600 font-sans hidden sm:inline ml-2">{{ $currentDate->year }}</span>
                        @endif
                    </h3>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="px-2 py-0.5 rounded bg-primary/10 text-primary text-[10px] font-black uppercase tracking-widest border border-primary/20">KW {{ $currentDate->weekOfYear }}</span>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest hidden sm:block">Zentrale Zeitverwaltung</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    {{-- Import Tool --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="h-12 px-4 bg-gray-900 border border-gray-700 text-gray-400 rounded-2xl shadow-lg flex items-center justify-center gap-2 hover:bg-gray-800 hover:text-white transition-all active:scale-95 shadow-inner">
                            <x-heroicon-m-arrow-up-tray class="w-4 h-4" />
                            <span class="hidden md:inline font-black uppercase tracking-[0.1em] text-[10px]">Import</span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 top-full mt-3 w-80 bg-gray-900 rounded-[2rem] shadow-[0_20px_50px_rgba(0,0,0,0.8)] border border-gray-700 p-6 z-50 animate-fade-in-up">
                            <h4 class="text-[10px] font-black text-white uppercase tracking-widest mb-4">ICS Kalender Import</h4>
                            <input type="file" wire:model="importFile" class="text-[10px] text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-gray-800 file:text-primary file:font-black file:uppercase file:text-[9px] cursor-pointer w-full mb-3 shadow-inner">
                            <div wire:loading wire:target="importFile" class="text-[10px] text-primary font-bold animate-pulse mb-2">Verarbeite Datei...</div>
                            @if($importFile)
                                <button wire:click="importEvents" class="w-full bg-primary text-gray-900 py-3 rounded-xl text-xs font-black uppercase tracking-widest shadow-glow hover:scale-[1.02] transition-transform">Import starten</button>
                            @endif
                        </div>
                    </div>

                    {{-- Neu Button --}}
                    <button wire:click="createEvent" class="h-12 px-6 bg-primary text-gray-900 rounded-2xl shadow-[0_0_20px_rgba(197,160,89,0.3)] flex items-center justify-center gap-2 hover:bg-white transition-all active:scale-95 group">
                        <x-heroicon-m-plus class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
                        <span class="hidden md:inline font-black uppercase tracking-[0.1em] text-[10px]">Neuer Eintrag</span>
                    </button>
                </div>
            </div>

            {{-- Untere Leiste: Navigation --}}
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex items-center justify-between bg-gray-950 p-1.5 rounded-2xl border border-gray-800 shadow-inner sm:w-auto">
                    <button wire:click="prev" class="p-2.5 hover:bg-gray-900 rounded-xl transition text-gray-500 hover:text-primary">
                        <x-heroicon-m-chevron-left class="w-5 h-5" />
                    </button>
                    <button wire:click="today" class="px-6 py-2 bg-gray-900 border border-gray-800 text-white shadow-lg rounded-xl text-[10px] font-black uppercase tracking-[0.2em] hover:border-primary/50 transition-all">Heute</button>
                    <button wire:click="next" class="p-2.5 hover:bg-gray-900 rounded-xl transition text-gray-500 hover:text-primary">
                        <x-heroicon-m-chevron-right class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex p-1.5 bg-gray-950 rounded-2xl border border-gray-800 shadow-inner overflow-x-auto no-scrollbar touch-pan-x gap-1">
                    @foreach(['year' => 'Jahr', 'month' => 'Monat', 'multi-week' => '4-W', 'week' => 'Woche', 'day' => 'Tag', 'list' => 'Liste'] as $v => $label)
                        <button wire:click="setView('{{ $v }}')"
                                class="whitespace-nowrap px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $view === $v ? 'bg-primary text-gray-900 shadow-glow' : 'text-gray-500 hover:text-gray-300' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 2. FEEDBACK MESSAGES --}}
    @if(session()->has('calendar_success'))
        <div class="bg-emerald-500/10 text-emerald-400 px-6 py-3 text-[10px] uppercase tracking-widest font-black text-center border-b border-emerald-500/20 flex justify-center items-center gap-2 animate-fade-in shadow-inner">
            <x-heroicon-s-check-circle class="w-4 h-4 drop-shadow-[0_0_8px_currentColor]" /> {{ session('calendar_success') }}
        </div>
    @endif

    {{-- 3. KALENDER CONTENT (SCROLLABLE) --}}
    <div class="flex-1 overflow-auto custom-scrollbar bg-gray-950/20">

        {{-- MONATS- & 4-WOCHEN GRID --}}
        @if($view === 'month' || $view === 'multi-week')
            <div class="min-w-[800px] h-full p-4 md:p-6">
                <div class="grid grid-cols-7 gap-3 h-full">
                    @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)
                        <div class="text-center py-2 text-[10px] font-black uppercase text-gray-600 tracking-[0.2em]">{{ $day }}</div>
                    @endforeach

                    @foreach($this->calendarGrid as $day)
                        <div @click="handleDateClick('{{ $day['date']->format('Y-m-d') }}')"
                             @dblclick="handleDateDblClick('{{ $day['date']->format('Y-m-d') }}')"
                            @class([
                           'min-h-[110px] bg-gray-900/40 rounded-[1.5rem] p-2 flex flex-col transition-all border relative cursor-pointer select-none group/day',
                           'border-gray-800 hover:border-gray-600 hover:bg-gray-800/60 shadow-inner' => $day['is_current_month'],
                           'border-transparent opacity-20' => !$day['is_current_month'],
                           'ring-2 ring-primary ring-offset-4 ring-offset-gray-950 z-10' => $day['is_today']
                       ])>
                            <div class="flex justify-between items-start mb-2">
                                <span @class([
                                    'text-xs font-black w-7 h-7 flex items-center justify-center rounded-lg transition-transform group-hover/day:scale-110',
                                    'bg-primary text-gray-900 shadow-glow' => $day['is_today'],
                                    'text-gray-500 bg-gray-950/50 border border-gray-800' => !$day['is_today'] && $day['is_current_month']
                                ])>
                                    {{ $day['date']->day }}
                                </span>
                                @if($day['date']->day === 1)
                                    <span class="text-[9px] font-black uppercase text-gray-700 tracking-tighter">{{ $day['date']->locale('de')->shortMonthName }}</span>
                                @endif
                            </div>

                            <div class="space-y-1.5 flex-1 overflow-y-auto no-scrollbar pr-1">
                                @foreach($day['events'] as $event)
                                    @php 
                                        $s = $styles[$event->category] ?? $styles['general']; 
                                        $spanType = $event->span_type ?? 'single';
                                        
                                        $roundedClass = 'rounded-lg';
                                        $borderClass = 'border-transparent hover:border-current/30';
                                        
                                        if ($spanType === 'start') {
                                            $roundedClass = 'rounded-l-lg rounded-r-none';
                                            $borderClass = 'border-y border-l border-r-0 border-transparent hover:border-current/30';
                                        } elseif ($spanType === 'middle') {
                                            $roundedClass = 'rounded-none';
                                            $borderClass = 'border-y border-x-0 border-transparent hover:border-current/30';
                                        } elseif ($spanType === 'end') {
                                            $roundedClass = 'rounded-l-none rounded-r-lg';
                                            $borderClass = 'border-y border-r border-l-0 border-transparent hover:border-current/30';
                                        }
                                    @endphp
                                    <div wire:click.stop="editEvent('{{ $event->id }}')"
                                         class="px-2 py-1 {{ $roundedClass }} {{ $s['bg'] }} {{ $s['text'] }} w-full text-[9px] font-bold truncate transition-all border {{ $borderClass }} shadow-lg flex items-center justify-between group/event" title="{{ $event->title }}">
                                        <span class="truncate">
                                            @if($spanType === 'start' || $spanType === 'single')
                                                {{ $event->title }}
                                            @else
                                                <span class="opacity-0">{{ $event->title }}</span>
                                            @endif
                                        </span>
                                        @if($event->reminder_minutes && ($spanType === 'start' || $spanType === 'single')) 
                                            <x-heroicon-s-bell class="w-2.5 h-2.5 opacity-50 shrink-0" /> 
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- JAHRESANSICHT --}}
        @elseif($view === 'year')
            <div class="p-6 md:p-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-8">
                @foreach($this->yearGrid as $month)
                    <div class="bg-gray-900/50 border border-gray-800 rounded-[2rem] p-6 shadow-2xl hover:border-primary/20 transition-all group">
                        <h4 class="text-sm font-black text-white mb-5 uppercase tracking-[0.2em] border-b border-gray-800 pb-3 group-hover:text-primary transition-colors">{{ $month['name'] }}</h4>
                        <div class="grid grid-cols-7 gap-1 text-center text-[8px] font-black text-gray-700 mb-2">
                            <span>M</span><span>D</span><span>M</span><span>D</span><span>F</span><span>S</span><span>S</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center">
                            @for($i = 0; $i < ($month['days'][0]['date']->dayOfWeekIso - 1); $i++) <div></div> @endfor
                            @foreach($month['days'] as $day)
                                <div class="aspect-square flex items-center justify-center cursor-pointer" wire:click="goToDay('{{ $day['date']->format('Y-m-d') }}')">
                                    <div @class([
                                        'w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-black transition-all',
                                        'bg-primary text-gray-900 shadow-glow scale-110' => $day['has_events'],
                                        'text-gray-600 hover:text-white hover:bg-gray-800' => !$day['has_events'],
                                        'border border-primary text-primary' => $day['date']->isToday() && !$day['has_events']
                                    ])>{{ $day['day'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- TERMINÜBERSICHT (LISTE) --}}
        @elseif($view === 'list')
            <div class="max-w-3xl mx-auto py-10 px-4 space-y-10"
                 x-init="$nextTick(() => { 
                    const todayEl = document.getElementById('list-day-today'); 
                    if(todayEl) { todayEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                 })">
                @php
                    $listDays = collect($this->calendarGrid)->filter(fn($day) => $day['date']->isSameMonth($currentDate) && count($day['events']) > 0);
                @endphp
                @foreach($listDays as $dayData)
                    @php $dayDate = $dayData['date']; @endphp
                    <div class="space-y-4" @if($dayDate->isToday()) id="list-day-today" @endif>
                        <div class="flex items-center gap-4 px-4">
                            <span class="text-[10px] font-black {{ $dayDate->isToday() ? 'text-primary' : 'text-gray-400' }} uppercase tracking-[0.3em]">{{ $dayDate->locale('de')->dayName }}</span>
                            <div class="h-px bg-gray-800 flex-1"></div>
                            <span class="text-xs font-bold {{ $dayDate->isToday() ? 'text-primary' : 'text-gray-600' }} font-mono">
                                @if($dayDate->isToday()) HEUTE @else {{ $dayDate->format('d.m.Y') }} @endif
                            </span>
                        </div>
                        <div @class([
                            'bg-gray-900/60 rounded-[2rem] p-3 border space-y-2 transition-all',
                            'ring-2 ring-primary ring-offset-4 ring-offset-gray-950 shadow-glow border-primary z-10' => $dayDate->isToday(),
                            'border-gray-800 shadow-2xl' => !$dayDate->isToday()
                        ])>
                            @foreach($dayData['events'] as $event)
                                @php 
                                    $s = $styles[$event->category] ?? $styles['general']; 
                                    $spanType = $event->span_type ?? 'single';
                                @endphp
                                <div class="flex items-center gap-4 p-4 hover:bg-gray-800 rounded-2xl cursor-pointer transition-all group border border-transparent hover:border-gray-700 shadow-sm" wire:click="editEvent('{{ $event->id }}')">
                                    <div class="w-14 h-14 bg-gray-950 rounded-xl border border-gray-800 shrink-0 flex flex-col items-center justify-center group-hover:border-primary/30 transition-colors shadow-inner">
                                        <span class="text-xl font-black text-white leading-none">{{ $dayDate->day }}</span>
                                        <span class="text-[9px] font-black text-gray-600 uppercase mt-1">{{ $dayDate->locale('de')->shortMonthName }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-bold text-white truncate group-hover:text-primary transition-colors">
                                            {{ $event->title }}
                                            @if($spanType !== 'single')
                                                <span class="text-[9px] text-gray-500 ml-2 font-mono uppercase">
                                                    @if($spanType === 'start') Beginn
                                                    @elseif($spanType === 'middle') Fortlaufend
                                                    @elseif($spanType === 'end') Endet heute
                                                    @endif
                                                </span>
                                            @endif
                                        </h4>
                                        <div class="flex items-center gap-3 mt-1.5">
                                            <span class="text-[8px] font-black uppercase px-2 py-1 rounded-md {{ $s['bg'] }} {{ $s['text'] }} border border-current/10 shadow-sm">{{ $s['label'] }}</span>
                                            <span class="text-[10px] font-mono font-bold text-gray-500">
                                                @if($event->is_all_day) Ganztägig @else {{ $event->start_date->format('H:i') }} @endif
                                            </span>
                                        </div>
                                    </div>
                                    <div class="w-10 h-10 rounded-full bg-gray-950 flex items-center justify-center text-gray-600 group-hover:text-primary group-hover:bg-gray-900 border border-gray-800 transition-all">
                                        <x-heroicon-m-chevron-right class="w-5 h-5" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if($this->events->isEmpty())
                    <div class="text-center py-20 bg-gray-900/40 rounded-[3rem] border border-dashed border-gray-800">
                        <x-heroicon-o-calendar class="w-12 h-12 text-gray-700 mx-auto mb-4" />
                        <p class="text-sm text-gray-500 font-medium italic">Keine Termine für diesen Zeitraum.</p>
                    </div>
                @endif
            </div>

            {{-- WOCHEN- / TAGESANSICHT --}}
        @elseif($view === 'week' || $view === 'day')
            <div class="max-w-4xl mx-auto py-10 px-4 space-y-10">
                @foreach($this->calendarGrid as $dayData)
                    <div class="relative pl-10 md:pl-16 pb-6">
                        {{-- Timeline Dot --}}
                        <div @class(['absolute left-0 top-1 w-5 h-5 rounded-full border-4 border-gray-950 z-10 shadow-sm transition-all', 'bg-primary ring-4 ring-primary/20 scale-125 shadow-glow' => $dayData['is_today'], 'bg-gray-700' => !$dayData['is_today']])></div>
                        @if(!$loop->last) <div class="absolute left-[9px] top-6 bottom-0 w-0.5 bg-gray-800"></div> @endif

                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 mb-6">
                            <h4 @class(['text-xl font-black uppercase tracking-widest', 'text-primary drop-shadow-[0_0_10px_rgba(197,160,89,0.5)]' => $dayData['is_today'], 'text-gray-500' => !$dayData['is_today']])>
                                {{ $dayData['date']->locale('de')->dayName }} <span class="text-white font-serif ml-2">{{ $dayData['date']->day }}.</span>
                            </h4>
                        </div>

                        <div class="grid gap-3">
                            @forelse($dayData['events'] as $event)
                                @php 
                                    $s = $styles[$event->category] ?? $styles['general']; 
                                    $spanType = $event->span_type ?? 'single';
                                @endphp
                                <div wire:click="editEvent('{{ $event->id }}')" class="bg-gray-900/60 border border-gray-800 rounded-[2rem] p-5 shadow-lg hover:shadow-2xl hover:border-primary/40 transition-all cursor-pointer flex items-center justify-between group/card">
                                    <div class="flex items-center gap-5">
                                        <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $s['bg'] }} {{ $s['text'] }} shadow-inner border border-current/10">
                                            <x-dynamic-component :component="'heroicon-o-' . $s['icon']" class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h5 class="text-base font-bold text-white group-hover/card:text-primary transition-colors">
                                                {{ $event->title }}
                                                @if($spanType !== 'single')
                                                    <span class="text-[10px] text-gray-500 ml-2 font-mono uppercase">
                                                        @if($spanType === 'start') (Beginn)
                                                        @elseif($spanType === 'middle') (Fortlaufend)
                                                        @elseif($spanType === 'end') (Endet heute)
                                                        @endif
                                                    </span>
                                                @endif
                                            </h5>
                                            <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-1">{{ $s['label'] }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[10px] font-mono font-black text-gray-400 bg-gray-950 px-3 py-1.5 rounded-lg border border-gray-800 shadow-inner">
                                            @if($event->is_all_day) TAG @else {{ $event->start_date->format('H:i') }} @endif
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="bg-gray-950/40 rounded-[1.5rem] p-4 border border-dashed border-gray-800 text-[10px] font-black text-gray-600 uppercase tracking-widest text-center">Keine Termine</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- 4. MODAL BEREICH (DARK EDITOR) --}}
    @if($showEditModal)
        <div class="fixed inset-0 z-[100] flex items-end md:items-center justify-center p-0 md:p-4 bg-black/80 backdrop-blur-md animate-fade-in">
            <div class="bg-gray-900 w-full md:max-w-xl h-[95vh] md:h-auto md:max-h-[90vh] rounded-t-[3rem] md:rounded-[3rem] shadow-[0_0_100px_rgba(0,0,0,1)] overflow-hidden flex flex-col transform animate-modal-up border-t md:border border-gray-800">

                {{-- Header --}}
                <div class="bg-gray-950/80 px-8 py-6 border-b border-gray-800 flex justify-between items-center shrink-0 shadow-inner">
                    <div>
                        <h3 class="text-2xl font-serif font-bold text-white tracking-tight">{{ $editingEventId ? 'Termin anpassen' : 'Neuer Plan' }}</h3>
                        <p class="text-[10px] font-black uppercase text-primary tracking-[0.3em] mt-1">Status: Automatische Synchronisation</p>
                    </div>
                    <button wire:click="closeModal" class="w-12 h-12 flex items-center justify-center bg-gray-900 border border-gray-700 rounded-2xl text-gray-500 hover:text-white hover:bg-red-500/20 hover:border-red-500 transition-all shadow-inner">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>

                {{-- Formular --}}
                <div class="flex-1 overflow-y-auto custom-scrollbar p-8 space-y-8 pb-32 md:pb-10 bg-gray-950/30">

                    {{-- Titel --}}
                    <div class="space-y-2">
                        <label class="label-xs">Was steht an?</label>
                        <input type="text" wire:model="editTitle" class="input-dark text-lg" placeholder="z.B. Team-Meeting">
                        @error('editTitle') <span class="text-red-400 text-[9px] font-black uppercase ml-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        {{-- Kategorie --}}
                        <div class="col-span-2 sm:col-span-1">
                            <label class="label-xs">Kategorie</label>
                            <div class="relative group">
                                <select wire:model="editCategory" class="input-dark appearance-none pr-10 cursor-pointer">
                                    @foreach($styles as $key => $style)
                                        <option value="{{ $key }}" class="bg-gray-900">{{ $style['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 group-focus-within:text-primary"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                            </div>
                        </div>
                        {{-- Erinnerung --}}
                        <div class="col-span-2 sm:col-span-1">
                            <label class="label-xs">Erinnerung</label>
                            <div class="relative group">
                                <select wire:model="editReminderMinutes" class="input-dark appearance-none pr-10 cursor-pointer">
                                    <option value="" class="bg-gray-900">Aus</option>
                                    <option value="0" class="bg-gray-900">Pünktlich</option>
                                    <option value="15" class="bg-gray-900">15 Min. vorher</option>
                                    <option value="60" class="bg-gray-900">1 Std. vorher</option>
                                    <option value="1440" class="bg-gray-900">1 Tag vorher</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 group-focus-within:text-primary"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                            </div>
                        </div>
                    </div>

                    {{-- Zeitstrahl --}}
                    <div class="bg-gray-900 rounded-[2rem] border border-gray-800 p-6 space-y-6 shadow-inner relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full blur-2xl pointer-events-none"></div>
                        <div class="flex items-center justify-between border-b border-gray-800 pb-4 relative z-10">
                            <span class="text-[10px] font-black uppercase text-gray-500 tracking-[0.2em]">Zeitraum</span>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" wire:model.live="editIsAllDay" class="rounded-lg border-gray-700 bg-gray-950 text-primary focus:ring-primary h-5 w-5">
                                <span class="text-[10px] font-black uppercase text-gray-400 group-hover:text-white transition-colors">Ganztägig</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 relative z-10">
                            <div class="space-y-3">
                                <span class="text-[9px] font-black text-primary uppercase ml-1 tracking-widest drop-shadow-[0_0_5px_currentColor]">Beginn</span>
                                <input type="date" wire:model="editStartDate" class="input-dark-sm [color-scheme:dark]">
                                @if(!$editIsAllDay)
                                    <input type="time" wire:model="editStartTime" class="input-dark-sm mt-2 [color-scheme:dark]">
                                @endif
                            </div>
                            <div class="space-y-3">
                                <span class="text-[9px] font-black text-primary uppercase ml-1 tracking-widest drop-shadow-[0_0_5px_currentColor]">Ende</span>
                                <input type="date" wire:model="editEndDate" class="input-dark-sm [color-scheme:dark]">
                                @if(!$editIsAllDay)
                                    <input type="time" wire:model="editEndTime" class="input-dark-sm mt-2 [color-scheme:dark]">
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Wiederholung --}}
                    <div class="space-y-2">
                        <label class="label-xs">Wiederholung (Serie)</label>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="relative group flex-1">
                                <select wire:model.live="editRecurrence" class="input-dark appearance-none pr-10 cursor-pointer">
                                    <option value="" class="bg-gray-900">Einmaliger Termin</option>
                                    <option value="daily" class="bg-gray-900">Täglich</option>
                                    <option value="weekly" class="bg-gray-900">Wöchentlich</option>
                                    <option value="monthly" class="bg-gray-900">Monatlich</option>
                                    <option value="yearly" class="bg-gray-900">Jährlich</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-600 group-focus-within:text-primary"><x-heroicon-m-chevron-down class="w-4 h-4"/></div>
                            </div>

                            @if($editRecurrence)
                                <div class="flex items-center gap-3 bg-gray-950 border border-gray-800 rounded-xl px-4 py-2 animate-fade-in shrink-0 shadow-inner">
                                    <span class="text-[9px] font-black uppercase text-gray-500">Endet:</span>
                                    <input type="date" wire:model="editRecurrenceEnd" class="bg-transparent border-0 p-0 text-xs font-bold text-white focus:ring-0 [color-scheme:dark] outline-none">
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Beschreibung --}}
                    <div class="space-y-2">
                        <label class="label-xs">Notizen & Details</label>
                        <textarea wire:model="editDescription" rows="3" class="input-dark resize-none text-sm placeholder:italic placeholder-gray-600" placeholder="Hinterlasse hier Details zum Termin..."></textarea>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="p-6 md:p-10 bg-gray-950/80 border-t border-gray-800 flex flex-row justify-between items-center gap-4 shrink-0 shadow-2xl">
                    @if($editingEventId)
                        <button wire:click="deleteEvent" wire:confirm="Soll dieser Termin (oder die Serie) wirklich gelöscht werden?" class="h-14 px-6 rounded-2xl text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500/10 transition-all border border-red-900/30 bg-gray-900 shadow-inner flex items-center gap-3 group">
                            <x-heroicon-o-trash class="w-5 h-5 transition-transform group-hover:scale-110" />
                            <span class="hidden sm:inline">Löschen</span>
                        </button>
                    @else
                        <div></div>
                    @endif
                    <div class="flex gap-4 flex-1 justify-end">
                        <button wire:click="closeModal" class="h-14 px-8 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-400 bg-gray-900 border border-gray-800 hover:text-white transition-all shadow-inner">
                            Abbrechen
                        </button>
                        <button wire:click="saveEvent" class="h-14 px-10 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-950 bg-primary hover:bg-white shadow-[0_0_30px_rgba(197,160,89,0.4)] transition-all active:scale-95 flex items-center gap-3">
                            <span wire:loading.remove wire:target="saveEvent">Speichern</span>
                            <span wire:loading wire:target="saveEvent" class="animate-pulse italic">Speichert...</span>
                            <x-heroicon-m-check class="w-5 h-5 stroke-2" />
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

        .label-xs { display: block; font-size: 9px; font-weight: 900; color: #6b7280; text-transform: uppercase; letter-spacing: 0.25em; margin-bottom: 8px; margin-left: 4px; }

        /* Zentrale Styling-Klasse für Dark-Inputs */
        .input-dark {
            width: 100%;
            background-color: #030712; /* gray-950 */
            border: 1px solid #1f2937; /* gray-800 */
            border-radius: 1.25rem;
            padding: 1rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.5);
            outline: none;
        }
        .input-dark:focus {
            border-color: #C5A059;
            box-shadow: 0 0 20px rgba(197, 160, 89, 0.1), inset 0 2px 4px rgba(0,0,0,0.5);
        }

        .input-dark-sm {
            width: 100%;
            background-color: #030712;
            border: 1px solid #1f2937;
            border-radius: 1rem;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 800;
            color: white;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);
            outline: none;
            transition: border-color 0.3s ease;
        }
        .input-dark-sm:focus {
            border-color: #C5A059;
        }

        .shadow-glow { box-shadow: 0 0 15px rgba(197, 160, 89, 0.3); }

        @media (max-width: 768px) {
            .animate-modal-up { animation: modal-slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
            @keyframes modal-slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
        }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #C5A059; }
    </style>
</div>
