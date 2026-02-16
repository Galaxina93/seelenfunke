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
