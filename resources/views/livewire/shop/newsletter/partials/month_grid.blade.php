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
