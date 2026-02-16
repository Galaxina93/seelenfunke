<div class="p-8 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-center bg-gray-50/30">

    {{-- View Switcher (Monat/Jahr) --}}
    <div class="flex items-center gap-1 bg-white rounded-xl p-1 border border-gray-200 shadow-sm">
        <button wire:click="$set('calendarView', 'month')"
                class="px-4 py-2 rounded-lg text-xs font-bold transition-all {{ $calendarView === 'month' ? 'bg-slate-900 text-white shadow-md' : 'text-gray-400 hover:bg-gray-50' }}">
            Monat
        </button>
        <button wire:click="$set('calendarView', 'year')"
                class="px-4 py-2 rounded-lg text-xs font-bold transition-all {{ $calendarView === 'year' ? 'bg-slate-900 text-white shadow-md' : 'text-gray-400 hover:bg-gray-50' }}">
            Jahr
        </button>
    </div>

    {{-- Year Navigation mit deinen Buttons --}}
    <div class="flex items-center justify-center gap-4">

        {{-- ZURÜCK --}}
        <button wire:click="$set('selectedYear', '{{ $selectedYear - 1 }}')" class="group focus:outline-none">
            <span class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center group-hover:border-gray-400 transition shadow-sm">
                <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </span>
        </button>

        {{-- JAHR ANZEIGE --}}
        <span class="text-2xl font-serif font-bold text-gray-900 min-w-[180px] text-center">
            {{ $calendarView === 'month' ? \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->locale('de')->isoFormat('MMMM YYYY') : $selectedYear }}
        </span>

        {{-- VOR (Pfeil gedreht) --}}
        <button wire:click="$set('selectedYear', '{{ $selectedYear + 1 }}')" class="group focus:outline-none">
            <span class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center group-hover:border-gray-400 transition shadow-sm">
                <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </span>
        </button>
    </div>

    {{-- Month Navigation (Nur sichtbar in Monatsansicht) --}}
    @if($calendarView === 'month')
        <div class="flex bg-white rounded-xl p-1 border border-gray-200">
            <button wire:click="$set('selectedMonth', '{{ $selectedMonth == 1 ? 12 : $selectedMonth - 1 }}'); @if($selectedMonth == 1) $set('selectedYear', '{{ $selectedYear - 1 }}') @endif"
                    class="p-2 hover:bg-gray-50 rounded-lg text-gray-400 transition-colors">
                <i class="bi bi-arrow-left-short fs-5"></i>
            </button>
            <button wire:click="$set('selectedMonth', '{{ $selectedMonth == 12 ? 1 : $selectedMonth + 1 }}'); @if($selectedMonth == 12) $set('selectedYear', '{{ $selectedYear + 1 }}') @endif"
                    class="p-2 hover:bg-gray-50 rounded-lg text-gray-400 transition-colors">
                <i class="bi bi-arrow-right-short fs-5"></i>
            </button>
        </div>
    @else
        <div class="w-24"></div>
    @endif
</div>
